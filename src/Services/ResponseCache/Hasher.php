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

        return 'responsecache-' .
            md5(
                "{$this->getHost(
                    $request,
                )}-{$request->getRequestUri()}-{$request->getMethod()}/$cacheNameSuffix",
            );
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
