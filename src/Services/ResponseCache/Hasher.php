<?php

namespace A17\EdgeFlush\Services\ResponseCache;

use Illuminate\Http\Request;
use Spatie\ResponseCache\Hasher\RequestHasher;
use Spatie\ResponseCache\CacheProfiles\CacheProfile;
use Spatie\ResponseCache\Hasher\DefaultHasher as SpatieHasher;

class Hasher extends SpatieHasher implements RequestHasher
{
    public function getHashFor(Request $request): string
    {
        $cacheNameSuffix = $this->getCacheNameSuffix($request);

        $host = $this->getHost($request);

        $uri = $request->getRequestUri();

        $method = $request->getMethod();

        info('hash '.'responsecache-' . md5("$host-$uri-$method-$cacheNameSuffix")."= $host-$uri-$method-$cacheNameSuffix");

        return 'responsecache-' . md5("$host-$uri-$method-$cacheNameSuffix");
    }

    public function getHost($request)
    {
        $url = $request->header('X-EDGE-FLUSH-WARMING-URL');

        if (blank($url)) {
            return $request->getHost();
        }

        $url = parse_url($url);

        return $url['host'] ?? '';
    }
}
