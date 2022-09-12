<?php

namespace A17\EdgeFlush\Services\ResponseCache;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use A17\EdgeFlush\Services\Invalidation;
use Spatie\ResponseCache\Hasher\RequestHasher;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\ResponseCache\ResponseCacheRepository;

class Service extends BaseService implements CDNService
{
    protected ResponseCacheRepository $cache;

    public function __construct()
    {
        $this->cache = app(ResponseCacheRepository::class);
    }

    public function invalidate(Collection $tags): Invalidation
    {
        if ($this->enabled()) {
            $tags->each(fn($tag) => $this->forget($tag->response_cache_hash));
        }

        return $this->successfulInvalidation();
    }

    public function invalidateAll(): Invalidation
    {
        if (!$this->enabled()) {
            return false;
        }

        /**
         * This is a call to a Laravel Façade which will try to
         * instantiate the class
         */
        ResponseCache::clear();

        return $this->successfulInvalidation();
    }

    public function makeResponseCacheTag($request): string|null
    {
        return $this->enabled()
            ? app(RequestHasher::class)->getHashFor($request)
            : null;
    }

    public function enabled(): bool
    {
        return EdgeFlush::enabled() && class_exists(ResponseCache::class);
    }

    public function forget($hash): void
    {
        $this->cache->has($hash) && $this->cache->forget($hash);
    }

    public function maxUrls(): int
    {
        return PHP_INT_MAX;
    }

    public function invalidationIsCompleted($invalidationId): bool
    {
        return true;
    }
}
