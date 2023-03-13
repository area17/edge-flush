<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Services\Entity;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Jobs\InvalidateModel;

trait CachedOnCDN
{
    public function invalidateCDNCache(Entity|Model $object): void
    {
        if (!$this->edgeFlushIsEnabled()) {
            return;
        }

        if ($object instanceof Model) {
            dispatch(new InvalidateModel(new Entity($object)));

            return;
        }

        EdgeFlush::tags()->dispatchInvalidationsForModel($object);
    }

    public function getCDNCacheTag(string $key = null): string
    {
        if (!$this->edgeFlushIsEnabled()) {
            return '';
        }

        /** @phpstan-ignore-next-line */
        return $this->attributes[$this->getKeyName()] ?? false
            ? static::class .
                    '[' .
                    /** @phpstan-ignore-next-line */
                    $this->getKey() .
                    ']' .
                    (filled($key) ? "[{$key}]" : '')
            : '';
    }

    public function cacheModelOnCDN(Model $model, string $key = null, array $allowedKeys = []): void
    {
        if (!$this->edgeFlushIsEnabled()) {
            return;
        }

        EdgeFlush::tags()->addTag($model, $key, $allowedKeys);
    }

    public function edgeFlushIsEnabled(): bool
    {
        return Helpers::configBool('edge-flush.enabled.package', false);
    }
}
