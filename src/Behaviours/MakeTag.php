<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Services\Invalidation;

trait MakeTag
{
    public function makeModelName(mixed $model, string $key = null, array $allowedKeys = []): string|null
    {
        if (!$model instanceof Model) {
            $model = json_encode($model);

            return $model === false ? null : sha1($model);
        }

        try {
            return method_exists($model, 'getCDNCacheTag') && $this->keyIsAllowed($key, $allowedKeys)
                ? $model->getCDNCacheTag($key)
                : null;
        } catch (\Exception $exception) {
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
}
