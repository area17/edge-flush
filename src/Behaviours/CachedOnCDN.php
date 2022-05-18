<?php

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Database\Eloquent\Model;

trait CachedOnCDN
{
    public function invalidateCDNCache(Model $model): void
    {
        $this->enabled() && EdgeFlush::tags()->invalidateTagsFor($model);
    }

    public function getCDNCacheTag(): string
    {
        return $this->attributes['id'] ?? false
            ? static::class . '-' . $this->attributes['id']
            : '';
    }

    public function cacheModelOnCDN(Model $model): void
    {
        EdgeFlush::tags()->addTag($model);
    }

    public function enabled()
    {
        return config('edge-flush.enabled');
    }
}
