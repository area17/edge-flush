<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\EdgeFlush as EdgeFlushFacade;

class Entity
{
    use MakeTag;

    public string $modelClass = '';

    public string $modelName = '';

    public int|string|null $id = null;

    public string $event = '';

    public array $attributes = [];

    public array $original = [];

    public bool $isValid = false;

    public array $relations = [];

    public function __construct(Model $model, string|null $event = null)
    {
        $this->absorb($model);

        $this->makeEvent($model, $event);
    }

    public function absorb(Model $model)
    {
        $model = EdgeFlushFacade::getInternalModel($model);

        $this->modelClass = get_class($model);

        $this->id = $model->getKey();

        $this->modelName = $this->makeModelName($model);

        $this->attributes = $this->absorbAttributes($model->getAttributes());

        $this->isValid = $this->tagIsNotExcluded($this->modelClass);

        foreach ($this->attributes as $key => $value) {
            $this->original[$key] = $this->absorbAttributes($model->getRawOriginal($key));
        }
    }

    public function isDirty(string|null $targetKey = null): bool
    {
        return $this->attributesAreDirty($targetKey) || $this->relationsAreDirty($targetKey);
    }

    public function attributesAreDirty(string|null $targetKey = null): bool
    {
        $dirty = false;

        foreach ($this->attributes as $key => $value) {
            $keyIsDirty = false;

            $updated = $this->encodeValueForComparison($value);

            $original = $this->encodeValueForComparison($this->original[$key], gettype($value));

            if ($updated !== $original && $this->granularPropertyIsAllowed($key, $this->modelClass)) {
                $keyIsDirty = true;
            }

            if ($targetKey === $key) {
                return $keyIsDirty;
            }

            $dirty = $dirty || $keyIsDirty;
        }

        return $dirty;
    }

    public function relationsAreDirty(string|null $targetKey = null): bool
    {
        $relations = $this->relations;

        if (filled($targetKey)) {
            $relations = [$targetKey => $this->relations[$targetKey] ?? null];
        }

        foreach ($relations as $relationName => $_) {
            if ($this->isRelationDirty($relationName)) {
                return true;
            }
        }

        return false;
    }

    public function mustInvalidate(): bool
    {
        return $this->createOrDeleteEvent($this->event) || $this->isDirty();
    }

    public function createOrDeleteEvent(string $event): bool
    {
        return in_array($event, ['created', 'deleted']);
    }

    public function makeEvent(Model $model, string|null $event = null): void
    {
        if (filled($event)) {
            $this->event = $event;

            return;
        }

        $this->event = $model->wasRecentlyCreated ? 'created' : 'updated';
    }

    public function getDirtyModelNames(): Collection
    {
        $modelNames = Helpers::collect();

        foreach ($this->attributes as $key => $value) {
            $updated = $this->encodeValueForComparison($value);

            $original = $this->encodeValueForComparison($this->original[$key], gettype($value));

            if ($updated !== $original && $this->granularPropertyIsAllowed($key, $this->modelName)) {
                if (filled($this->modelName)) {
                    $modelName = "{$this->modelName}[attribute:{$key}]";

                    Helpers::debug("ATTRIBUTE CHANGED: {$modelName}");

                    $modelNames->push($modelName);
                }
            }
        }

        foreach ($this->relations as $name => $changed) {
            if ($this->isRelationDirty($name) && $this->granularPropertyIsAllowed($name, $this->modelName)) {
                if (filled($this->modelName)) {
                    $modelName = "{$this->modelName}[relation:{$name}]";

                    Helpers::debug("RELATION CHANGED: {$modelName}");

                    $modelNames->push($modelName);
                }
            }
        }

        return $modelNames;
    }

    public function getOriginal(string $key): mixed
    {
        return $this->original[$key] ?? null;
    }

    public function getNew(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function attributeEquals(string $key, mixed $value): bool
    {
        $attribute = $this->encodeValueForComparison($this->attributes[$key] ?? null);

        $original = $this->encodeValueForComparison($value, gettype($attribute));

        return $attribute === $original;
    }

    public function setRelation(array $relation): void
    {
        if (blank($name = $relation['name'] ?? null)) {
            return;
        }

        $this->relations[$name] = $relation;
    }

    public function isRelationDirty(string|null $key): bool
    {
        if (empty($key)) {
            return false;
        }

        foreach ($this->relations[$key] ?? [] as $value) {
            if (filled($value)) {
                return true;
            }
        }

        return false;
    }

    public function absorbAttributes(array $attributes): array
    {
        $absorbed = [];

        foreach ($attributes as $key => $value) {
            $absorbed[$key] = $this->absorbAttributeValue($value);
        }

        return $absorbed;
    }

    protected function absorbAttributeValue(mixed $value): mixed
    {
        // Too long strings are hashed to avoid hitting SQS limits
        if (is_string($value) && strlen($value) > 512) {
            return sha1($value);
        }

        return $value;
    }
}
