<?php

namespace A17\CDN\Behaviours;

use A17\CDN\CDN;
use Illuminate\Database\Eloquent\Model;

trait CachedOnCDN
{
    public function invalidateCDNCache(Model $model): void
    {
        CDN::tags()->purgeTagsFor($model);
    }

    public function getCDNCacheTag(): string
    {
        return $this->attributes['id'] ?? false
            ? static::class . '-' . $this->attributes['id']
            : '';
    }

    public function cacheModelOnCDN(Model $model): void
    {
        CDN::tags()->addTag($model);
    }
}
