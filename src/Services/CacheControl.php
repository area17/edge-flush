<?php

namespace A17\EdgeFlush\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Constants;
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

        return $this->stripCookies(
            $this->addHeadersToResponse($response, 'cache-control', $strategy),
            $strategy,
        );
    }

    protected function contentContains(Response $response, string $string): bool
    {
        return Str::contains(
            $this->getContent($response),
            $this->minifyContent($string),
        );
    }

    public function isCachable(Response $response): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        if (filled($this->_isCachable)) {
            return $this->_isCachable;
        }

        return $this->_isCachable = !$this->getCachableMatrix(
            $response,
        )->contains(false);
    }

    public function getCachableMatrix(Response $response): Collection
    {
        return collect([
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
                config(
                    'edge-flush.default-strategies.non-cachable-http-methods',
                ),
            );
        }

        if ($this->containsValidForm($response)) {
            return $this->buildStrategy(
                config('edge-flush.default-strategies.pages-with-valid-forms'),
            );
        }

        return $this->isCachable($response)
            ? $this->buildStrategy(
                config('edge-flush.default-strategies.cachable-requests'),
            )
            : $this->buildStrategy(
                config('edge-flush.default-strategies.non-cachable-requests'),
            );
    }

    protected function getContent(Response $response): string
    {
        $getContentFromResponse =
            !filled($this->_content) &&
            filled($response) &&
            !($response instanceof BinaryFileResponse);

        if ($getContentFromResponse) {
            if (method_exists($response, 'content')) {
                $this->_content = $response->content();
            } elseif (method_exists($response, 'getContent')) {
                $this->_content = $response->getContent();
            }

            $this->_content = $this->minifyContent((string) $this->_content);
        }

        if ($this->_content === false)
        {
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

        if (config('edge-flush.valid_forms.enabled', false)) {
            $hasForm = collect(
                config('edge-flush.valid_forms.strings', false),
            )->reduce(function (bool $hasForm, string $string) use ($response) {
                $string = Str::replace('%CSRF_TOKEN%', csrf_token(), $string);

                $hasForm =
                    $hasForm && $this->contentContains($response, $string);

                return $hasForm;
            }, true);
        }

        return $hasForm;
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
            ? 'no-middleware'
            : $route->action['middleware'] ?? null;

        return !collect($middleware)->contains('doNotCacheResponse');
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

        $default =
            $field === 's-maxage'
                ? $this->getDefaultSMaxAge()
                : $this->getDefaultMaxAge();

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
        return (int) config('edge-flush.s-maxage.default', Constants::MS_WEEK);
    }

    public function getDefaultMaxAge(): int
    {
        return (int) config('edge-flush.max-age.default', 0);
    }

    public function buildStrategy(string|null $strategy): string
    {
        return collect($this->getStrategyArray($strategy))
            ->map(
                fn(string $header) => [
                    'header' => $header,
                    'value' => $this->getHeaderValue($header),
                ],
            )
            ->map(
                fn(array $item) => $item['header'] === $item['value']
                    ? $item['header']
                    : "{$item['header']}={$item['value']}",
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

        return (collect(config('edge-flush.responses.cachable'))->isEmpty() ||
            collect(config('edge-flush.responses.cachable'))->contains(
                get_class($response),
            )) &&
            !collect(config('edge-flush.responses.not-cachable'))->contains(
                get_class($response),
            );
    }

    public function methodIsCachable(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return (collect(config('edge-flush.methods.cachable'))->isEmpty() ||
            collect(config('edge-flush.methods.cachable'))->contains(
                EdgeFlush::getRequest()->getMethod(),
            )) &&
            !collect(config('edge-flush.methods.not-cachable'))->contains(
                EdgeFlush::getRequest()->getMethod(),
            );
    }

    public function statusCodeIsCachable(Response $response): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return (collect(config('edge-flush.statuses.cachable'))->isEmpty() ||
            collect(config('edge-flush.statuses.cachable'))->contains(
                $response->getStatusCode(),
            )) &&
            !collect(config('edge-flush.statuses.not-cachable'))->contains(
                $response->getStatusCode(),
            );
    }

    public function routeIsCachable(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $route = EdgeFlush::getRequest()->route();

        $route = $route instanceof Route ? $route->getName() : null;

        if (empty($route)) {
            return config('edge-flush.routes.cache_nameless_routes', false);
        }

        /**
         * @param callable(string $pattern): boolean $filter
         */
        $filter = fn(string $pattern) => EdgeFlush::match($pattern, $route);

        return (collect(config('edge-flush.routes.cachable'))->isEmpty() ||
            collect(config('edge-flush.routes.cachable'))->contains($filter)) &&
            !collect(config('edge-flush.routes.not-cachable'))->contains(
                $filter,
            );
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

        return (collect(config('edge-flush.urls.cachable'))->isEmpty() ||
            collect(config('edge-flush.urls.cachable'))->contains($filter)) &&
            !collect(config('edge-flush.urls.not-cachable'))->contains($filter);
    }

    public function stripCookies(Response $response, string $strategy): Response
    {
        $strip = config('edge-flush.strip_cookies');

        /**
         * We only strip cookies from cachable responses because those cookies (potentially logged in users), if cached by the CDN
         * would be the same for everyone hitting the website.
         */
        if (!filled($strip) || !$this->willBeCached($response, $strategy)) {
            return $response;
        }

        collect($response->headers->getCookies())->each(function ($cookie) use (
            $response,
            $strip
        ) {
            if ($this->matchAny($cookie->getName(), $strip)) {
                $response->headers->removeCookie($cookie->getName());
            }
        });

        return $response;
    }

    public function getStrategyArray(string|null $strategy): array
    {
        if (!$this->enabled() || empty($strategy)) {
            return config('edge-flush.strategies.zero', []);
        }

        $strategy = config(
            "edge-flush.built-in-strategies.$strategy",
            $strategy,
        );

        return config("edge-flush.strategies.$strategy", []);
    }

    public function willBeCached(Response $response, string|null $strategy = null): bool
    {
        $strategy ??= $this->getCacheStrategy($response);

        return $this->isCachable($response) &&
            $this->strategyDoesntContainsNoStoreDirectives($strategy);
    }

    public function strategyDoesntContainsNoStoreDirectives(string $strategy): bool
    {
        return collect(explode(',', $strategy))->reduce(function (
            $willCache,
            $element
        ) {
            $element = trim($element);

            return $willCache &&
                $element !== 's-maxage=0' &&
                $element !== 'max-age=0' &&
                $element !== 'no-store';
        },
        true);
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
