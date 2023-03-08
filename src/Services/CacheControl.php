<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Carbon\Carbon;
use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Support\Constants;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Contracts\Service as ServiceContract;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use A17\EdgeFlush\Exceptions\FrontendChecker as FrontendCheckerException;

class CacheControl extends BaseService implements ServiceContract
{
    protected bool|null $_isCachable = null;

    protected string|null|false $_content = null;

    protected int|null $maxAge = null;

    protected int|null $sMaxAge = null;

    protected string|null $strategy = null;

    public function makeResponse(Response $response): Response
    {
        if (!$this->enabled()) {
            return $response;
        }

        $strategy = $this->getCacheStrategy($response);

        return $this->stripCookies($this->addHeadersToResponse($response, 'cache-control', $strategy), $strategy);
    }

    protected function contentContains(Response $response, string $string): bool
    {
        return Str::contains($this->getContent($response), $this->minifyContent($string));
    }

    public function isCachable(Response $response): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        if (filled($this->_isCachable)) {
            return (bool) $this->_isCachable;
        }

        return $this->_isCachable = !$this->getCachableMatrix($response)->contains(false);
    }

    public function getCachableMatrix(Response $response): Collection
    {
        return Helpers::collect([
            'enabled' => $this->enabled(),
            'isFrontend' => $this->isFrontend(),
            'notValidForm' => !$this->containsValidForm($response),
            'methodIsCachable' => $this->methodIsCachable(),
            'middlewareAllowCaching' => $this->middlewaresAllowCaching(),
            'routeIsCachable' => $this->routeIsCachable(),
            'urlIsCachable' => $this->urlIsCachable(),
            'responseIsCachable' => $this->responseIsCachable($response),
            'statusCodeIsCachable' => $this->statusCodeIsCachable($response),
        ]);
    }

    public function getCacheStrategy(Response $response): string
    {
        if (filled($this->strategy)) {
            return $this->buildStrategy($this->strategy);
        }

        if (!$this->methodIsCachable()) {
            return $this->buildStrategy(
                Helpers::configString('edge-flush.default-strategies.non-cachable-http-methods'),
            );
        }

        if ($this->containsValidForm($response)) {
            return $this->buildStrategy(Helpers::configString('edge-flush.default-strategies.pages-with-valid-forms'));
        }

        return $this->isCachable($response)
            ? $this->buildStrategy(Helpers::configString('edge-flush.default-strategies.cachable-requests'))
            : $this->buildStrategy(Helpers::configString('edge-flush.default-strategies.non-cachable-requests'));
    }

    protected function getContent(Response $response): string
    {
        $getContentFromResponse =
            !filled($this->_content) && filled($response) && !($response instanceof BinaryFileResponse);

        if ($getContentFromResponse) {
            if (method_exists($response, 'content')) {
                $this->_content = $response->content();
            } else {
                $this->_content = $response->getContent();
            }

            $this->_content = $this->minifyContent((string) $this->_content);
        }

        if ($this->_content === false) {
            return '';
        }

        return $this->_content ?? '';
    }

    public function getMaxAge(): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        if (filled($this->maxAge)) {
            return $this->maxAge ?? 0;
        }

        return $this->getDefaultMaxAge();
    }

    public function getSMaxAge(): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        if (filled($this->sMaxAge)) {
            return $this->sMaxAge ?? 0;
        }

        return $this->getDefaultSMaxAge();
    }

    protected function containsValidForm(Response $response): bool
    {
        $hasForm = false;

        if (Helpers::configBool('edge-flush.valid_forms.enabled', false)) {
            $hasForm = Helpers::collect(Helpers::configArray('edge-flush.valid_forms.strings'))->reduce(function (
                bool $hasForm,
                mixed $string
            ) use ($response) {
                if (!is_string($string)) {
                    return $hasForm;
                }

                $string = Str::replace('%CSRF_TOKEN%', csrf_token(), $string);

                return $hasForm && $this->contentContains($response, $string);
            },
            true);
        }

        return !!$hasForm;
    }

    protected function isFrontend(): bool
    {
        $checker = config('edge-flush.frontend-checker');

        if (is_callable($checker)) {
            return $checker();
        }

        if (is_bool($checker)) {
            return $checker;
        }

        if (is_string($checker) && class_exists($checker)) {
            return app($checker)->runningOnFrontend();
        }

        FrontendCheckerException::unsupportedType(gettype($checker));

        return false;
    }

    protected function minifyContent(string $content): string
    {
        return str_replace(' ', '', $content);
    }

    protected function middlewaresAllowCaching(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $middleware = blank($route = EdgeFlush::getRequest()->route())
            ? ['no-middleware']
            : $route->action['middleware'] ?? null;

        if (blank($middleware) || is_null($middleware)) {
            return false;
        }

        return !Helpers::collect($middleware)->contains('doNotCacheResponse');
    }

    public function setBrowserMaxAge(int|string $maxAge): self
    {
        return $this->setMaxAge($maxAge);
    }

    public function setCDNMaxAge(int|string $maxAge): self
    {
        return $this->setSMaxAge($maxAge);
    }

    public function setSMaxAge(int|string $maxAge): self
    {
        return $this->__setMaxAge($maxAge, 's-maxage');
    }

    public function setMaxAge(int|string $maxAge): self
    {
        return $this->__setMaxAge($maxAge, 'max-age');
    }

    protected function __setMaxAge(int|string $age, string $field): self
    {
        if (is_string($age)) {
            $age = $this->parseAgeString($age);
        }

        if (blank($age)) {
            return $this;
        }

        $strategy = config("edge-flush.$field.strategy");

        $property = $field === 's-maxage' ? 'sMaxAge' : 'maxAge';

        $default = $field === 's-maxage' ? $this->getDefaultSMaxAge() : $this->getDefaultMaxAge();

        if ($this->$property === null) {
            $this->$property = $age;
        } else {
            if ($strategy === 'min') {
                $this->$property = min($age, $this->$property ?? $default);
            }

            if ($strategy === 'last') {
                $this->$property = $age;
            }
        }

        return $this;
    }

    public function getDefaultSMaxAge(): int
    {
        return Helpers::configInt('edge-flush.s-maxage.default') ?? Constants::MS_WEEK;
    }

    public function getDefaultMaxAge(): int
    {
        return Helpers::configInt('edge-flush.max-age.default') ?? 0;
    }

    public function buildStrategy(string|null $strategy): string
    {
        return Helpers::collect($this->getStrategyArray($strategy))
            ->map(
                fn(mixed $header) => [
                    'header' => $header,
                    'value' => is_string($header) ? $this->getHeaderValue($header) : null,
                ],
            )
            ->map(
                fn(mixed $item) => $item['header'] === $item['value']
                    ? $item['header']
                    : (is_string($item['header'])
                        ? "{$item['header']}={$item['value']}"
                        : null),
            )
            ->sort()
            ->join(', ');
    }

    /**
     * @param string $header
     * @return int|string
     */
    public function getHeaderValue(string $header)
    {
        if ($header === 's-maxage') {
            return $this->getSMaxAge();
        }

        if ($header === 'max-age') {
            return $this->getMaxAge();
        }

        if (
            $header === 'max-stale' ||
            $header === 'min-fresh' ||
            $header === 'stale-while-revalidate' ||
            $header === 'stale-if-error'
        ) {
            return 'unsupported';
        }

        return $header;
    }

    public function getStrategy(): string|null
    {
        return $this->strategy ?? null;
    }

    public function setStrategy(string $strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function responseIsCachable(Response $response): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return (Helpers::collect(config('edge-flush.responses.cachable'))->isEmpty() ||
            Helpers::collect(config('edge-flush.responses.cachable'))->contains(get_class($response))) &&
            !Helpers::collect(config('edge-flush.responses.not-cachable'))->contains(get_class($response));
    }

    public function methodIsCachable(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return (Helpers::collect(config('edge-flush.methods.cachable'))->isEmpty() ||
            Helpers::collect(config('edge-flush.methods.cachable'))->contains(EdgeFlush::getRequest()->getMethod())) &&
            !Helpers::collect(config('edge-flush.methods.not-cachable'))->contains(
                EdgeFlush::getRequest()->getMethod(),
            );
    }

    public function statusCodeIsCachable(Response $response): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return (Helpers::collect(config('edge-flush.statuses.cachable'))->isEmpty() ||
            Helpers::collect(config('edge-flush.statuses.cachable'))->contains($response->getStatusCode())) &&
            !Helpers::collect(config('edge-flush.statuses.not-cachable'))->contains($response->getStatusCode());
    }

    public function routeIsCachable(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $route = EdgeFlush::getRequest()->route();

        $route = (string) ($route instanceof Route ? $route->getName() : null);

        if (blank($route)) {
            return Helpers::configBool('edge-flush.routes.cache_nameless_routes', false);
        }

        /**
         * @param callable(string $pattern): boolean $filter
         */
        $filter = fn(string $pattern) => EdgeFlush::match($pattern, $route);

        return (Helpers::collect(config('edge-flush.routes.cachable'))->isEmpty() ||
            Helpers::collect(config('edge-flush.routes.cachable'))->contains($filter)) &&
            !Helpers::collect(config('edge-flush.routes.not-cachable'))->contains($filter);
    }

    public function urlIsCachable(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $url = EdgeFlush::getRequest()->url();

        /**
         * @param callable(string $pattern): boolean $filter
         */
        $filter = fn(string $pattern) => EdgeFlush::match($pattern, $url);

        return (Helpers::collect(config('edge-flush.urls.cachable'))->isEmpty() ||
            Helpers::collect(config('edge-flush.urls.cachable'))->contains($filter)) &&
            !Helpers::collect(config('edge-flush.urls.not-cachable'))->contains($filter);
    }

    public function stripCookies(Response $response, string $strategy): Response
    {
        $strip = Helpers::configArray('edge-flush.strip_cookies') ?? [];

        /**
         * We only strip cookies from cachable responses because those cookies (potentially logged in users), if cached by the CDN
         * would be the same for everyone hitting the website.
         */
        if (!filled($strip) || !$this->willBeCached($response, $strategy)) {
            return $response;
        }

        Helpers::collect($response->headers->getCookies())->each(function ($cookie) use ($response, $strip) {
            if ($cookie instanceof Cookie && $this->matchAny($name = $cookie->getName(), $strip)) {
                $response->headers->removeCookie($name);
            }
        });

        return $response;
    }

    public function getStrategyArray(string|null $strategyName): array
    {
        if (!$this->enabled() || $strategyName === null) {
            return Helpers::configArray('edge-flush.strategies.zero') ?? [];
        }

        $strategyName = Helpers::configString("edge-flush.built-in-strategies.$strategyName") ?? $strategyName;

        if (trim($strategyName) === '') {
            return [];
        }

        return Helpers::configArray("edge-flush.strategies.$strategyName") ?? [];
    }

    public function willBeCached(Response $response, string|null $strategy = null): bool
    {
        $strategy ??= $this->getCacheStrategy($response);

        return $this->isCachable($response) && $this->strategyDoesntContainsNoStoreDirectives($strategy);
    }

    public function strategyDoesntContainsNoStoreDirectives(string $strategy): bool
    {
        return !!Helpers::collect(explode(',', $strategy))->reduce(function ($willCache, $element) {
            if (!is_string($element)) {
                return $willCache;
            }

            $element = trim($element);

            return $willCache && $element !== 's-maxage=0' && $element !== 'max-age=0' && $element !== 'no-store';
        }, true);
    }

    public function parseAgeString(string $age): int
    {
        try {
            return now()->diffInSeconds(Carbon::parse($age));
        } catch (\Throwable $error) {
            return 0;
        }
    }
}
