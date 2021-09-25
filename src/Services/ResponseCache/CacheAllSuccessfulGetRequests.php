<?php

namespace A17\EdgeFlush\Services\ResponseCache;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests as SpatieCacheAllSuccessfulGetRequests;

class CacheAllSuccessfulGetRequests extends SpatieCacheAllSuccessfulGetRequests
{
    public function shouldCacheRequest(Request $request): bool
    {
        if ($request->ajax()) {
            return false;
        }

        if ($this->isRunningInConsole() && !$this->isWarming($request)) {
            return false;
        }

        return $request->isMethod('get');
    }

    public function hasCacheableResponseCode(Response $response): bool
    {
        $isCacheable = parent::hasCacheableResponseCode($response);

        if (!$isCacheable) {
            Log::error(sprintf('Response is not cacheable: %s - %s', $response->getStatusCode(), $response->getContent()));
        }

        return $isCacheable;
    }

    public function isWarming($request): bool
    {
        return filled($request->header('X-EDGE-FLUSH-WARMING-URL', null));
    }
}
