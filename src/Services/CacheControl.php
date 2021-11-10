<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Constants;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Contracts\Service as ServiceContract;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use A17\EdgeFlush\Exceptions\FrontendChecker as FrontendCheckerException;

class CacheControl extends BaseService implements ServiceContract
{
    protected $_isCachable;

    protected $_content;

    protected $public;

    protected $maxAge;

    protected $strategy;

    public function __construct()
    {
        $this->instantiate();
    }

    public function makeResponse(Response $response): Response
    {
        if (!EdgeFlush::enabled()) {
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

    public function isCachable(Response $response = null): bool
    {
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
            'enabled' => EdgeFlush::enabled(),
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
            return $this->buildStrategy('zero-cache');
        }

        if ($this->containsValidForm($response)) {
            return $this->buildStrategy('zero-cache');
        }

        return $this->isCachable($response)
            ? $this->buildStrategy('cache')
            : $this->buildStrategy('micro-cache');
    }

    protected function getContent(Response $response): string
    {
        return $this->_content = filled($this->_content)
            ? $this->_content
            : $this->minifyContent($response->getContent());
    }

    public function getMaxAge(): int
    {
        if (filled($this->maxAge)) {
            return $this->maxAge;
        }

        return $this->getDefaultMaxAge();
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

        /**
         * @phpstan-ignore-next-line
         */
        FrontendCheckerException::unsupportedType(gettype($checker));
    }

    protected function minifyContent(string $content): string
    {
        return str_replace(' ', '', $content);
    }

    protected function middlewaresAllowCaching(): bool
    {
        $middleware = blank($route = EdgeFlush::getRequest()->route())
            ? 'no-middleware'
            : $route->action['middleware'] ?? null;

        return !collect($middleware)->contains('doNotCacheResponse');
    }

    public function setMaxAge(int $maxAge): self
    {
        if (blank($maxAge)) {
            return $this;
        }

        if (config('edge-flush.max-age.strategy') === 'min') {
            $this->maxAge = min(
                $maxAge,
                $this->maxAge ?? $this->getDefaultMaxAge(),
            );
        }

        if (config('edge-flush.max-age.strategy') === 'last') {
            $this->maxAge = $maxAge;
        }

        return $this;
    }

    public function getDefaultMaxAge(): int
    {
        return (int) config('edge-flush.max-age.default', Constants::WEEK);
    }

    public function buildStrategy(string $strategy): string
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
        if ($header === 'max-age' || $header === 's-maxage') {
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

    public function getStrategy(): ?string
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
        $route = EdgeFlush::getRequest()->route();

        $route = filled($route) ? $route->getName() : null;

        if (blank($route)) {
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
        $url = EdgeFlush::getRequest()->url();

        /**
         * @param callable(string $pattern): boolean $filter
         */
        $filter = fn(string $pattern) => EdgeFlush::match($pattern, $url);

        return (collect(config('edge-flush.urls.cachable'))->isEmpty() ||
            collect(config('edge-flush.urls.cachable'))->contains($filter)) &&
            !collect(config('edge-flush.urls.not-cachable'))->contains($filter);
    }

    public function stripCookies($response, $strategy)
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

    public function getStrategyArray($strategy)
    {
        $strategy = config(
            "edge-flush.built-in-strategies.$strategy",
            $strategy,
        );

        return config("edge-flush.strategies.$strategy", []);
    }

    public function willBeCached($response = null, $strategy = null)
    {
        $strategy ??= $this->getCacheStrategy($response);

        return $this->isCachable($response) &&
            $this->strategyDoesntContainsNoStoreDirectives($strategy);
    }

    public function strategyDoesntContainsNoStoreDirectives($strategy)
    {
        return collect(explode(',', $strategy))->reduce(function (
            $willCache,
            $element
        ) {
            $element = trim($element);

            return $willCache &&
                $element !== 'max-age=0' &&
                $element !== 'no-store';
        },
        true);
    }
}
