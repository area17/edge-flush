<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\EdgeFlush as EdgeFlushFacade;
use Illuminate\Support\Collection;

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

        $this->attributes = $model->getAttributes();

        $this->isValid = $this->tagIsNotExcluded($this->modelClass);

        foreach ($this->attributes as $key => $value) {
            $this->original[$key] = $model->getRawOriginal($key);
        }
    }

    public function isDirty()
    {
        foreach ($this->attributes as $key => $value) {
            $updated = $this->encodeValueForComparison($value);

            $original = $this->encodeValueForComparison($this->original[$key], gettype($value));

            if ($updated !== $original && $this->granularPropertyIsAllowed($key, $this->modelClass)) {
                $dirty = true;
            }
        }

        return $dirty ?? false;
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
                $modelName = "{$this->modelName}[{$key}]";

                Helpers::debug("ATTRIBUTE CHANGED: {$modelName}");

                if (filled($modelName)) {
                    $modelNames->push($modelName);
                }
            }
        }

        return $modelNames;
    }
}
