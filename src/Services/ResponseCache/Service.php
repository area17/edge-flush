<?php

namespace A17\EdgeFlush\Services\ResponseCache;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use Spatie\ResponseCache\Hasher\RequestHasher;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\ResponseCache\ResponseCacheRepository;

class Service extends BaseService implements CDNService
{
    protected ResponseCacheRepository $cache;

    public function invalidate(Collection $tags): bool
    {
        if ($this->enabled()) {
            $tags->each(fn($tag) => $this->forget($tag->response_cache_hash));
        }

        return true;
    }

    public function invalidateAll(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        /**
         * This is a call to a Laravel Façade which will try to
         * instantiate the class
         */
        ResponseCache::clear();

        return true;
    }

    public function makeResponseCacheTag($request): ?string
    {
        return $this->enabled()
            ? app(RequestHasher::class)->getHashFor($request)
            : null;
    }

    public function enabled()
    {
        return $this->enabled() && class_exists(ResponseCache::class);
    }

    public function forget($hash): void
    {
        $this->cache->has($hash) && $this->cache->forget($hash);
    }
}
