<?php

namespace A17\CDN\Services;

use Faker\Provider\Base;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use A17\CDN\Services\ResponseCache\Service as ResponseCache;

class CDN extends BaseService
{
    public BaseService $cdnService;

    public CacheControl $cacheControl;

    public Tags $tags;

    public Warmer $warmer;

    public ResponseCache $responseCache;

    public Request $request;

    public bool $enabled;

    public function __construct(
        BaseService $cdnService,
        CacheControl $cacheControl,
        Tags $tags,
        Warmer $warmer,
        ResponseCache $responseCache
    ) {
        $this->cdnService = $cdnService;

        $this->cacheControl = $cacheControl;

        $this->tags = $tags;

        $this->warmer = $warmer;

        $this->responseCache = $responseCache;

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
            $this->cdnService->makeResponse($response)
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

    public function responseCache(): ResponseCache
    {
        return $this->responseCache;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getRequest()
    {
        return $this->request;
    }
}
