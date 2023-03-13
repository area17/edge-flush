<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Support\Helpers;
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

        try {
            return method_exists($model, 'getCDNCacheTag') && $this->keyIsAllowed($key, $allowedKeys)
                ? $model->getCDNCacheTag($key)
                : $this->getCDNCacheTagFromModel($model, $key);
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

    public function getCDNCacheTagFromModel(mixed $model, string $key = null): string|null
    {
        $model = $this->getInternalModel($model);

        if ($this->tagIsExcluded(get_class($model))) {
            return null;
        }

        return $model->getCDNCacheTag($key);
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
}
