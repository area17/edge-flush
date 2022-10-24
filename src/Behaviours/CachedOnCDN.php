<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Database\Eloquent\Model;

trait CachedOnCDN
{
    public function invalidateCDNCache(Model $model): void
    {
        $this->edgeFlushIsEnabled() &&
            EdgeFlush::tags()->dispatchInvalidationsForModel($model);
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

    public function edgeFlushIsEnabled(): bool
    {
        return config('edge-flush.enabled', false);
    }
}
