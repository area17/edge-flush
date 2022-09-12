<?php

namespace A17\EdgeFlush\Services;

use Faker\Provider\Base;
use Illuminate\Http\Request;
use A17\EdgeFlush\Contracts\CDNService;
use Symfony\Component\HttpFoundation\Response;

class EdgeFlush extends BaseService
{
    public CDNService $cdn;

    public CacheControl $cacheControl;

    public Tags $tags;

    public Warmer $warmer;

    public Request $request;

    public function __construct(
        CDNService $cdn,
        CacheControl $cacheControl,
        Tags $tags,
        Warmer $warmer
    ) {
        $this->cdn = $cdn;

        $this->cacheControl = $cacheControl;

        $this->tags = $tags;

        $this->warmer = $warmer;

        $this->enabled = config('edge-flush.enabled', false);
    }

    public function makeResponse(Response $response): Response
    {
        if (!$this->enabled()) {
            return $response;
        }

        return $this->cacheControl->makeResponse(
            $this->cdn->makeResponse($response),
        );
    }

    public function instance(): self
    {
        return $this;
    }

    public function cdn(): CDNService
    {
        return $this->cdn;
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

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function storeTagsServiceIsEnabled(): bool
    {
        return $this->enabled() &&
            config('edge-flush.enabled-services.store-tags', false);
    }

    public function invalidationServiceIsEnabled(): bool
    {
        return $this->enabled() &&
            config('edge-flush.enabled-services.invalidation', false);
    }

    public function warmerServiceIsEnabled(): bool
    {
        return $this->enabled() && config('edge-flush.warmer.enabled', false);
    }
}
