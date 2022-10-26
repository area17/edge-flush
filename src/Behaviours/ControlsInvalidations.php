<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\Services\Invalidation;

trait ControlsInvalidations
{
    public function successfulInvalidation(): Invalidation
    {
        return (new Invalidation())->setSuccess(true);
    }

    public function unsuccessfulInvalidation(): Invalidation
    {
        return (new Invalidation())->setSuccess(false);
    }
}
