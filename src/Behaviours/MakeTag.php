<?php

namespace A17\EdgeFlush\Behaviours;

use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Services\Invalidation;

trait MakeTag
{
    public function makeModelName(Model $model, string $key = null, array $allowedKeys = []): string|null
    {
        if ($key === 'title') {


        info(['------ makeModelName ----', get_class($model), $key, $allowedKeys, $this->keyIsAllowed($key, $allowedKeys), $model->getCDNCacheTag($key)]);
        }
        
        try {
            return method_exists($model, 'getCDNCacheTag') && $this->keyIsAllowed($key, $allowedKeys)
                ? $model->getCDNCacheTag($key)
                : null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function keyIsAllowed(string $key = null, array $allowedKeys = [])
    {
        if ($allowedKeys === []) {
            return true;
        }

        return collect($allowedKeys)->contains($key);
    }
}
