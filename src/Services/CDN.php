<?php

namespace A17\CDN\Services;

use Symfony\Component\HttpFoundation\Response;

class CDN extends BaseService
{
    public $cdnService;

    public $cacheControl;

    public $tags;

    public $enabled;

    public function __construct(
        BaseService $cdnService,
        CacheControl $cacheControl,
        Tags $tags
    ) {
        $this->cdnService = $cdnService;

        $this->cacheControl = $cacheControl;

        $this->tags = $tags;
    }

    public function enabled()
    {
        if (filled($this->enabled)) {
            return $this->enabled;
        }

        return $this->enabled = config('cdn.enabled', true);
    }

    public function makeResponse($response): Response
    {
        if (!$this->enabled()) {
            return $response;
        }

        return $this->cacheControl->makeResponse(
            $this->cdnService->makeResponse($response),
        );
    }

    public function cdn()
    {
        return $this->cdnService;
    }

    public function cacheControl()
    {
        return $this->cacheControl;
    }

    public function tags()
    {
        return $this->tags;
    }

    public function cdnService()
    {
        return $this->cdnService;
    }

    public function match($patten, $string)
    {
        $patten = str_replace('\\', '_', $patten);

        $string = str_replace('\\', '_', $string);

        return fnmatch($patten, $string);
    }
}
