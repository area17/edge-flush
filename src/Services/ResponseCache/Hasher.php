<?php

namespace A17\EdgeFlush\Services\ResponseCache;

use Illuminate\Http\Request;
use A17\EdgeFlush\Support\Helpers;
use Spatie\ResponseCache\Hasher\RequestHasher;
use Spatie\ResponseCache\CacheProfiles\CacheProfile;
use Spatie\ResponseCache\Hasher\DefaultHasher as SpatieHasher;

class Hasher extends SpatieHasher implements RequestHasher
{
    public function getHashFor(Request $request): string
    {
        $cacheName = $this->getCacheNameSuffix($request);

        $host = $this->getHost($request);

        $uri = $request->getRequestUri();

        $method = $request->getMethod();

        return 'responsecache-' . md5("$host-$uri-$method-$cacheName");
    }

    public function getHost(Request $request): string
    {
        $url = $request->header('X-Edge-Flush-Warmed-Url');

        if (blank($url)) {
            return $request->getHost();
        }

        return Helpers::parseUrl($url)['host'] ?? '';
    }
}
