<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use Illuminate\Support\Str;
use A17\CDN\Support\Constants;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CacheControl
{
    protected $_isCachable;

    protected $_content;

    protected $public;

    protected $maxAge;

    protected $strategy;

    public function addHttpHeadersToResponse($response)
    {
        $strategy = $this->getCacheStrategy($response);

        collect(config('cdn.headers.cache-control'))->each(
            fn($header) => $response->header($header, $strategy),
        );

        return $response;
    }

    protected function contentContains($response, string $string): bool
    {
        return Str::contains(
            $this->getContent($response),
            $this->minifyContent($string),
        );
    }

    public function isCachable($response): bool
    {
        if (filled($this->_isCachable)) {
            return $this->_isCachable;
        }

        return CDN::enabled() &&
            $this->isFrontend() &&
            $this->doesNotContainAValidForm($response) &&
            $this->middlewaresAllowCaching() &&
            $this->routeIsCachable($response) &&
            $this->responseIsCachable($response) &&
            $this->methodIsCachable() &&
            $this->statusCodeIsCachable($response);
    }

    public function getCacheStrategy($response): string
    {
        if (filled($this->strategy)) {
            return $this->buildStrategy($this->strategy);
        }

        return $this->isCachable($response)
            ? $this->buildStrategy('cache')
            : $this->buildStrategy('do-not-cache');
    }

    protected function getContent($response)
    {
        if (
            !filled($this->_content) &&
            filled($response) &&
            !($response instanceof BinaryFileResponse)
        ) {
            $this->_content = $this->minifyContent($response->content());
        }

        return $this->_content;
    }

    public function getMaxAge(): int
    {
        if (filled($this->maxAge)) {
            return $this->maxAge;
        }

        return $this->getDefaultMaxAge();
    }

    protected function doesNotContainAValidForm($response): bool
    {
        $hasForm = false;

        if (config('cdn.valid_forms.enabled', false)) {
            $hasForm = collect(
                config('cdn.valid_forms.strings', false),
            )->reduce(function ($hasForm, $string) use ($response) {
                $string = Str::replace('%CSRF_TOKEN%', csrf_token(), $string);

                $hasForm = $hasForm && $this->contentContains($response, $string);
            }, true);
        }

        return !$hasForm;
    }

    protected function isFrontend(): bool
    {
        $checker = config('cdn.frontend-checker');

        if (is_callable($checker)) {
            return $checker();
        }

        return $checker ?? false;
    }

    protected function minifyContent(string $content)
    {
        return str_replace(' ', '', $content);
    }

    protected function middlewaresAllowCaching(): bool
    {
        $middleware = blank($route = request()->route())
            ? 'no-middleware'
            : $route->action['middleware'];

        return !collect($middleware)->contains('doNotCacheResponse');
    }

    /**
     * @param mixed $maxAge
     * @return CacheControl
     */
    public function setMaxAge($maxAge): self
    {
        if (blank($maxAge)) {
            return $this;
        }

        if (config('cdn.max-age.strategy') === 'min') {
            $this->maxAge = min(
                $maxAge,
                $this->maxAge ?? $this->getDefaultMaxAge(),
            );
        }

        if (config('cdn.max-age.strategy') === 'last') {
            $this->maxAge = $maxAge;
        }

        return $this;
    }

    public function getDefaultMaxAge()
    {
        return config('cdn.max-age.default', Constants::WEEK);
    }

    public function buildStrategy($strategy)
    {
        return collect(config("cdn.strategies.$strategy"))
            ->map(
                fn($header) => [
                    'header' => $header,
                    'value' => $this->getHeaderValue($header),
                ],
            )
            ->map(
                fn($item) => $item['header'] === $item['value']
                    ? $item['header']
                    : "{$item['header']}={$item['value']}",
            )
            ->sort()
            ->join(', ');
    }

    public function getHeaderValue($header)
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

    public function setStrategy($strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function responseIsCachable($response): bool
    {
        return (collect(config('cdn.responses.cachable'))->isEmpty() ||
            collect(config('cdn.responses.cachable'))->contains(
                get_class($response),
            )) &&
            !collect(config('cdn.responses.not-cachable'))->contains(
                get_class($response),
            );
    }

    public function methodIsCachable(): bool
    {
        return (collect(config('cdn.methods.cachable'))->isEmpty() ||
            collect(config('cdn.methods.cachable'))->contains(
                request()->getMethod(),
            )) &&
            !collect(config('cdn.methods.not-cachable'))->contains(
                request()->getMethod(),
            );
    }

    public function statusCodeIsCachable($response): bool
    {
        return (collect(config('cdn.statuses.cachable'))->isEmpty() ||
            collect(config('cdn.statuses.cachable'))->contains(
                $response->getStatusCode(),
            )) &&
            !collect(config('cdn.statuses.not-cachable'))->contains(
                $response->getStatusCode(),
            );
    }

    public function routeIsCachable(): bool
    {
        $route = request()->route();

        $route = filled($route) ? $route->getName() : 'newsletter';

        $filter = fn($pattern) => fnmatch($pattern, $route);

        return (collect(config('cdn.routes.cachable'))->isEmpty() ||
            collect(config('cdn.routes.cachable'))->contains($filter)) &&
            !collect(config('cdn.routes.not-cachable'))->contains($filter);
    }
}
