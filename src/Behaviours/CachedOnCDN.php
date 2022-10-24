<?php

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;

trait CachedOnCDN
{
    public function invalidateCDNCache(Model $model): void
    {
        $this->edgeFlushIsEnabled() &&
            EdgeFlush::tags()->dispatchInvalidationsForModel($model);
    }

    public function getCDNCacheTag(string $key = null): string
    {
        return $this->attributes['id'] ?? false
            ? static::class . '-' . $this->attributes['id'] . (filled($key) ? "[{$key}]" : '')
            : '';
    }

    public function cacheModelOnCDN(Model $model, string $key = null, array $allowedKeys = []): void
    {
        EdgeFlush::tags()->addTag($model, $key, $allowedKeys);
    }

    public function edgeFlushIsEnabled(): bool
    {
        return config('edge-flush.enabled', false);
    }
}
