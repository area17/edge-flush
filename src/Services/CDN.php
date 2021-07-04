<?php

namespace A17\CDN\Services;

class CDN
{
    public $cdnService;

    public $cacheControl;

    public function __construct(BaseService $cdnService, CacheControl $cacheControl)
    {
        $this->cdnService = $cdnService;

        $this->cacheControl = $cacheControl;
    }

    public function enabled()
    {
        return config('cdn.enabled', true);
    }

    public function addHttpHeadersToResponse($response)
    {
        if (!$this->enabled()) {
            return $response;
        }

        return $this->cacheControl->addHttpHeadersToResponse(
            $this->cdnService->addHttpHeadersToResponse($response),
        );
    }

    public function getCDNServiceInstance()
    {
        return $this->cdnService;
    }

    public function getCacheControlInstance()
    {
        return $this->cacheControl;
    }
}
