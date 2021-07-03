<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use Illuminate\Support\Str;
use A17\CDN\Support\Constants;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CacheControl
{
    /**
     * @var \Illuminate\Http\Response|null
     */
    protected $response;

    protected $_enabled;

    protected $_content;

    protected $_forceCache = false;

    protected $public;

    protected $maxAge;

    public function makeResponse($response)
    {
        return $this->responseCanBeCached($response)
            ? $this->cachedResponse($response)
            : $this->cacheDisabledResponse($response);
    }

    public function responseCanBeCached($response): bool
    {
        return CDN::responseIsCachable($response) &&
            CDN::routeIsCachable() &&
            CDN::methodIsCachable() &&
            CDN::statusCodeIsCachable($response);
    }

    public function addCacheHeaders(
        SymfonyResponse $response,
        $value
    ): SymfonyResponse {
        $this->response = $response;

        collect(config('cdn.headers.cache-control'))->each(
            fn($header) => $response->header($header, $value),
        );

        return $response;
    }

    public function cachedResponse(SymfonyResponse $response): SymfonyResponse
    {
        return $this->addCacheHeaders($response, $this->getCacheStrategyFor($response));
    }

    public function cacheDisabledResponse(
        SymfonyResponse $response
    ): SymfonyResponse {
        return $this->addCacheHeaders($response, $this->getDoNotCacheStrategy());
    }

    protected function contentContains(string $string): bool
    {
        return Str::contains(
            $this->getContent(),
            $this->minifyContent($string),
        );
    }

    public function enabled(): bool
    {
        if (filled($this->_enabled)) {
            return $this->_enabled;
        }

        return $this->isForced() ||
            ($this->isFrontend() &&
                $this->doesNotContainAValidForm() &&
                CDN::routeIsCachable() &&
                $this->middlewaresAllowCaching());
    }

    public function disabled(): bool
    {
        return !$this->enabled();
    }

    protected function getDoNotCacheStrategy(): string
    {
        return config('cdn.strategies.do-not-cache');
    }

    protected function getCacheStrategyFor(SymfonyResponse $response): string
    {
        if ($this->disabled()) {
            return $this->getDoNotCacheStrategy();
        }

        return collect([$this->getPublic(), $this->getMaxAgeCache()])->join(
            ',',
        );
    }

    protected function getContent()
    {
        if (
            !filled($this->_content) &&
            filled($this->response) &&
            !($this->response instanceof BinaryFileResponse)
        ) {
            $this->_content = $this->minifyContent($this->response->content());
        }

        return $this->_content;
    }

    protected function getPublic(): ?string
    {
        return $this->enabled() ? 'public' : null;
    }

    public function getMaxAge(): int
    {
        if (filled($this->maxAge)) {
            return $this->maxAge;
        }

        return $this->getDefaultMaxAge();
    }

    public function getMaxAgeCache(): ?string
    {
        return $this->enabled() ? 'max-age=' . $this->getMaxAge() : null;
    }

    protected function doesNotContainAValidForm(): bool
    {
        $hasForm = false;

        if (config('cdn.valid_forms.enabled', false)) {
            $hasForm = collect(
                config('cdn.valid_forms.strings', false),
            )->reduce(function ($hasForm, $string) {
                $string = Str::replace('%CSRF_TOKEN%', csrf_token(), $string);

                $hasForm = $hasForm && $this->contentContains($string);
            }, true);
        }

        return !$hasForm;
    }

    protected function isForced(): bool
    {
        return filled($this->maxAge) || $this->_forceCache;
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
        if (filled($maxAge)) {
            $this->maxAge = min(
                $maxAge,
                $this->maxAge ?? $this->getDefaultMaxAge(),
            );
        }

        return $this;
    }

    public function forceCache($maxAge = null)
    {
        $this->_forceCache = true;

        $this->setMaxAge($maxAge);
    }

    public function responseIsCachable()
    {
        return $this->enabled();
    }

    public function getDefaultMaxAge()
    {
        return config('cdn.cache-control.max-age', Constants::WEEK);
    }
}
