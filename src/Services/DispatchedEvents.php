<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

class DispatchedEvents
{
    protected $dispatched = [];

    public function register($model)
    {
        $this->dispatched[$model] = true;
    }

    public function alreadyDispatched($model)
    {
        $dispatched = $this->dispatched[$model] ?? false;

        $this->register($model);
        
        return $dispatched;
    }
}
