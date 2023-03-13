<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Jobs\InvalidateModel;

trait CachedOnCDN
{
    public function invalidateCDNCache(Model $model, string|null $type = null): void
    {
        if ($this->edgeFlushIsEnabled()) {
            dispatch(new InvalidateModel($model, $type));
        }
    }

    public function getCDNCacheTag(string $key = null): string
    {
        /** @phpstan-ignore-next-line */
        return $this->attributes[$this->getKeyName()] ?? false
            ? static::class .
                    '-' .
                    /** @phpstan-ignore-next-line */
                    $this->getAttributes()[$this->getKeyName()] .
                    (filled($key) ? "[{$key}]" : '')
            : '';
    }

    public function cacheModelOnCDN(Model $model, string $key = null, array $allowedKeys = []): void
    {
        EdgeFlush::tags()->addTag($model, $key, $allowedKeys);
    }

    public function edgeFlushIsEnabled(): bool
    {
        return Helpers::configBool('edge-flush.enabled.package', false);
    }
}
