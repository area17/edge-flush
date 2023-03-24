<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Services\Entity;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Services\Invalidation;

trait MakeTag
{
    use CastObject;

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
            Helpers::debug("Exception on makeModelName: ".$exception->getMessage());

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

        return $model->getCDNCacheTag($key, $type);
    }

    public function tagIsExcluded(string $tag): bool
    {
        /**
         * @param callable(string $pattern): boolean $pattern
         */
        return Helpers::collect(config('edge-flush.tags.excluded-model-classes'))->contains(
            fn(string $pattern) => EdgeFlush::match($pattern, $tag),
        );
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

        if ($type === 'boolean' || is_bool($value)) {
            return !!$value ? 'true' : 'false';
        }

        if ($type === 'string' || is_string($value)) {
            return "'$value'";
        }

        if ($type === 'array' || is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    public function granularPropertyIsAllowed(string $name, Model|string $model): bool
    {
        $ignored = Helpers::collect(Helpers::configArray('edge-flush.invalidations.properties.ignored'));

        $model = $model instanceof Model ? get_class($model) : $model;

        return !$ignored->contains($name) && !$ignored->contains("$model@$name");
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
}
