<?php

declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use Illuminate\Support\Str;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Services\Entity;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Services\Invalidation;
use Illuminate\Database\Eloquent\Relations\Relation;

trait MakeTag
{
    use CastObject;

    protected Collection $excludedModels;

    public function makeModelName(mixed $model, string $key = null, array $allowedKeys = []): string|null
    {
        if (!$model instanceof Model) {
            $model = json_encode($model);

            return $model === false ? null : sha1($model);
        }

        $type = blank($key) ? null : ($this->isRelation($model, $key) ? 'relation' : 'attribute');

        try {
            return method_exists($model, 'getCDNCacheTag') && $this->keyIsAllowed($key, $allowedKeys)
                ? $model->getCDNCacheTag($key, $type)
                : $this->getCDNCacheTagFromModel($model, $key, $type);
        } catch (\Exception $exception) {
            Helpers::debug('MAKE-TAG:EXCEPTION: on makeModelName: ' . $exception->getMessage());

            return null;
        }
    }

    public function keyIsAllowed(string $key = null, array $allowedKeys = []): bool
    {
        if ($allowedKeys === []) {
            return true;
        }

        return Helpers::collect($allowedKeys)->contains($key);
    }

    public function getCDNCacheTagFromModel(mixed $model, string $key = null, string|null $type = null): string|null
    {
        $model = $this->getInternalModel($model);

        if ($this->tagIsExcluded(get_class($model))) {
            return null;
        }

        /** @phpstan-ignore-next-line */
        return $model->getCDNCacheTag($key, $type);
    }

    public function tagIsExcluded(string $tag): bool
    {
        $this->excludedModels ??= Helpers::collect(config('edge-flush.strategies.tags.excluded-model-classes'));

        /**
         * @param callable(string $pattern): boolean $pattern
         */
        return $this->excludedModels->contains(fn(string $pattern) => EdgeFlush::match($pattern, $tag));
    }

    public function tagIsNotExcluded(string $tag): bool
    {
        return !$this->tagIsExcluded($tag);
    }

    public function encodeValueForComparison(mixed $value, string|null $type = null): string
    {
        if ($type === 'NULL' || is_null($value)) {
            return 'null';
        }

        if ($type === 'integer' && is_numeric($value)) {
            return (string) $value;
        }

        if ($type === 'boolean' || is_bool($value)) {
            return (bool) $value ? '1' : '0';
        }

        if ($value instanceof \Carbon\Carbon) {
            $value = (string) $value;
        }

        if ($type === 'string' || $type === 'array' || $type === 'object') {
            if (is_string($value)) {
                return "'$value'";
            }

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);

                if (is_string($value)) {
                    return $value;
                }
            }
        }

        /// FIXME: we cannot cast this value to a string, so we are generating a random string that will not match anything else, fow now
        return '--- cannot cast to string --- ' . Str::random(16);
    }

    public function granularAttributeIsAllowed(string $name, Model|string $model): bool
    {
        return !$this->attributeMustBeIgnored($model, $name);
    }

    public function attributeExists(Model $model, string $attribute): bool
    {
        $attributes = $model->getAttributes();

        if (isset($attributes[$attribute])) {
            return true;
        }

        return $this->isRelation($model, $attribute);
    }

    public function isRelation(Model $model, string $attribute): bool
    {
        if (!method_exists($model, $attribute)) {
            return false;
        }

        try {
            $relation = $model->$attribute();
        } catch (\Throwable $e) {
            return false;
        }

        return $relation instanceof Relation;
    }

    protected function attributeMustBeIgnored(Model|string $model, string $attribute): bool
    {
        $model = $model instanceof Model ? get_class($model) : $model;

        $attributes = Helpers::configArray('edge-flush.invalidations.attributes.ignore', []);

        $ignore = array_merge($attributes[$model] ?? [], $attributes['*'] ?? []);

        return in_array($attribute, $ignore);
    }
}
