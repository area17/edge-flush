<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\Behaviours\MakeTag;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;

class ModelChanged
{
    use MakeTag;

    protected string $model = '';

    protected string $modelName = '';

    protected array $attributes = [];

    protected array $original = [];

    public function __construct(Model $model)
    {
        $this->absorb($model);
    }

    public function absorb(Model $model)
    {
        $this->model = get_class($model);

        $this->modelName = $this->makeModelName($model);

        $this->attributes = $model->getAttributes();

        foreach ($this->attributes as $key => $value) {
            $this->original[$key] = $model->getRawOriginal($key);
        }
    }

    public function isDirty()
    {
        foreach ($this->attributes as $key => $value) {
            $updated = $this->encodeValueForComparison($value);

            $original = $this->encodeValueForComparison($this->original[$key], gettype($value));

            if ($updated !== $original && $this->granularPropertyIsAllowed($key, $this->model)) {
                Helpers::debug("MODEL DIRTY: {$this->model} {$key}");

                Helpers::debug("MODEL DIRTY: ORIGINAL: {$this->original[$key]}");

                Helpers::debug("MODEL DIRTY: NEW: {$this->attributes[$key]}");

                Helpers::debug("---------------------------------------------------------");

                $dirty = true;
            }
        }

        return $dirty ?? false;
    }
}
