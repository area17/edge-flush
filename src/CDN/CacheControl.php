<?php

namespace App\Services\CDN;

use App\Support\Constants;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CacheControl
{
    const DEFAULT_MAX_AGE = Constants::WEEK;

    /**
     * @var \Illuminate\Http\Response|null
     */
    protected $response;

    protected $_enabled;

    protected $_content;

    protected $_forbiddenRoutes = [
        'pdf.ticket',
        'awallet.ticket',
        'gwallet.ticket',
        'newsletter.store',
        'newsletter',
        'api.',
    ];
    protected $_forceCache = false;

    protected $public;

    protected $maxAge;

    public function cache(SymfonyResponse $response): SymfonyResponse
    {
        $this->response = $response;

        return $response->header(
            'Cache-Control',
            $this->getCacheStrategyFor($response),
        );
    }

    public function noCache(SymfonyResponse $response): SymfonyResponse
    {
        $this->response = $response;

        if ($this->response instanceof BinaryFileResponse) {
            return $this->response;
        }

        return $response->header('Cache-Control', $this->getNoCacheStrategy());
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
                $this->doesNotContainsAValidForm() &&
                $this->routeIsCachable() &&
                $this->middlewaresAllowCaching());
    }

    public function disabled(): bool
    {
        return !$this->enabled();
    }

    protected function getNoCacheStrategy(): string
    {
        return 'no-store, private';
    }

    protected function getCacheStrategyFor(SymfonyResponse $response): string
    {
        if ($this->disabled()) {
            return $this->getNoCacheStrategy();
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

        if (is_api_call()) {
            return Constants::SECOND * 20;
        }

        return self::DEFAULT_MAX_AGE;
    }

    public function getMaxAgeCache(): ?string
    {
        return $this->enabled() ? 'max-age=' . $this->getMaxAge() : null;
    }

    protected function doesNotContainsAValidForm(): bool
    {
        return !(
            $this->contentContains('<form') &&
            $this->contentContains(
                '<input type="hidden" name="_token" value="' .
                    csrf_token() .
                    '"',
            )
        );
    }

    protected function isForced(): bool
    {
        return filled($this->maxAge) || $this->_forceCache;
    }

    protected function isFrontend(): bool
    {
        return is_running_on_frontend();
    }

    protected function minifyContent(string $content)
    {
        return str_replace(' ', '', $content);
    }

    protected function middlewaresAllowCaching(): bool
    {
        return !collect(request()->route()->action['middleware'])->contains(
            'doNotCacheResponse',
        );
    }

    protected function routeIsCachable(): bool
    {
        return !collect($this->_forbiddenRoutes)->contains(
            request()
                ->route()
                ->getName(),
        );
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
                $this->maxAge ?? static::DEFAULT_MAX_AGE,
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
}
