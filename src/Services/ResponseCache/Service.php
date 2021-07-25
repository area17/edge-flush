<?php

namespace A17\CDN\Services\ResponseCache;

use A17\CDN\Services\BaseService;
use A17\CDN\Contracts\CDNService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\ResponseCache\Hasher\RequestHasher;
use Spatie\ResponseCache\Facades\ResponseCache;

class Service extends BaseService implements CDNService
{
    public function invalidate(Collection $items): bool
    {
        if ($this->enabled()) {
            $items->each(
                fn($item) => Cache::forget($item->response_cache_hash),
            );
        }

        return true;
    }

    public function invalidateAll(): bool
    {
        return true;
    }

    public function makeResponseCacheTag($request): ?string
    {
        if (!$this->enabled()) {
            return null;
        }

        return app(RequestHasher::class)->getHashFor($request);
    }

    public function enabled()
    {
        return class_exists(ResponseCache::class);
    }
}
