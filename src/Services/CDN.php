<?php

namespace A17\CDN\Services;

use Faker\Provider\Base;
use Symfony\Component\HttpFoundation\Response;

class CDN extends BaseService
{
    public BaseService $cdnService;

    public CacheControl $cacheControl;

    public Tags $tags;

    public Warmer $warmer;

    public bool $enabled;

    public function __construct(
        BaseService $cdnService,
        CacheControl $cacheControl,
        Tags $tags,
        Warmer $warmer
    ) {
        $this->cdnService = $cdnService;

        $this->cacheControl = $cacheControl;

        $this->tags = $tags;

        $this->warmer = $warmer;

        $this->enabled = config('cdn.enabled', true);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function makeResponse(Response $response): Response
    {
        if (!$this->enabled()) {
            return $response;
        }

        return $this->cacheControl->makeResponse(
            $this->cdnService->makeResponse($response),
        );
    }

    public function instance(): self
    {
        return $this;
    }

    public function cdn(): BaseService
    {
        return $this->cdnService;
    }

    public function cacheControl(): CacheControl
    {
        return $this->cacheControl;
    }

    public function tags(): Tags
    {
        return $this->tags;
    }

    public function warmer(): Warmer
    {
        return $this->warmer;
    }
}
