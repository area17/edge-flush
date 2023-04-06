<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array $attributes
 */
trait CachedOnCDN
{
    public function invalidateCDNCache(Model $model): void
    {
        if ($this->edgeFlushIsEnabled() && $this->invalidationsAreEnabled()) {
            EdgeFlush::tags()->dispatchInvalidationsForModel($model);
        }
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
        return Helpers::configBool('edge-flush.enabled', false);
    }

    public function invalidationsAreEnabled()
    {
        return Helpers::configBool('edge-flush.enabled-services.invalidation', false);
    }
}
