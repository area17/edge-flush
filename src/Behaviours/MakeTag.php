<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Services\Invalidation;

trait MakeTag
{
    public function makeModelName(mixed $model): string|null
    {
        if (!$model instanceof Model) {
            $model = json_encode($model);

            return $model === false ? null : sha1($model);
        }

        try {
            return method_exists($model, 'getCDNCacheTag')
                ? $model->getCDNCacheTag()
                : null;
        } catch (\Exception $exception) {
            return null;
        }
    }
}
