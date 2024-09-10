<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Builder;
use A17\EdgeFlush\Services\Entity;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Jobs\InvalidateModel;
use GeneaLabs\LaravelPivotEvents\Traits\PivotEventTrait;

trait CachedOnCDN
{
    protected array $edgeFlushCachedAttributes = [];

    public function newEloquentBuilder($query)
    {
        return new \A17\EdgeFlush\Models\Builder($query);
    }

    public function invalidateCDNCache(Entity|Model $object): void
    {
        if (!$this->edgeFlushIsEnabled() || !$this->invalidationsAreEnabled()) {
            return;
        }

        if ($object instanceof Model) {
            dispatch(new InvalidateModel(new Entity($object)));

            return;
        }

        EdgeFlush::tags()->dispatchInvalidationsForModel($object);
    }

    public function getCDNCacheTag(string $key = null, string|null $type = null): string
    {
        if (!$this->edgeFlushIsEnabled()) {
            return '';
        }

        $type ??= 'attribute';

        $granular = filled($key) && Helpers::configBool('edge-flush.enabled.granular_invalidation')
                    ? "[{$type}:{$key}]"
                    : '';

        /** @phpstan-ignore-next-line */
        return $this->attributes[$this->getKeyName()] ?? false
            ? static::class .
                    '[' .
                        /** @phpstan-ignore-next-line */
                        $this->getKey() .
                    ']' . $granular
            : '';
    }

    public function cacheModelOnCDN(Model $model, string $key, array $allowedKeys = []): void
    {
        if (!$this->edgeFlushKeyWasAlreadyAdded($key)) {
            return;
        }

        if (!$this->edgeFlushIsEnabled()) {
            return;
        }

        EdgeFlush::tags()->addTag($model, $key, $allowedKeys);
    }

    public function edgeFlushIsEnabled(): bool
    {
        return Helpers::configBool('edge-flush.enabled.package', false);
    }

    public function invalidationsAreEnabled(): bool
    {
        return Helpers::configBool('edge-flush.enabled.services.invalidation', false);
    }

    public function edgeFlushKeyWasAlreadyAdded(string $key): bool
    {
        $added = !in_array($key, $this->edgeFlushCachedAttributes);

        $this->edgeFlushCachedAttributes[$key] = $key;

        return $added;
    }

    public function setOriginalAttributes(array $attributes): void
    {
        $this->original = $attributes;
    }
}
