<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

class DispatchedEvents
{
    protected array $dispatched = [];

    public function register(string $model): void
    {
        $this->dispatched[$model] = true;
    }

    public function alreadyDispatched(string $model): bool
    {
        $dispatched = $this->dispatched[$model] ?? false;

        $this->register($model);

        return $dispatched;
    }
}
