<?php declare(strict_types=1);

namespace A17\EdgeFlush\Listeners;

use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use A17\EdgeFlush\Services\ModelChanged;
use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Contracts\Queue\ShouldQueue;
use A17\EdgeFlush\Services\DispatchedEvents;

class EloquentObserver
{
    use CachedOnCDN, MakeTag;

    protected DispatchedEvents|null $dispatchedEvents = null;

    public function __construct()
    {
        $this->boot();
    }

    public function created(Model $model): void
    {
        $this->invalidate($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model, 'updated');
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model, 'deleted');
    }

    public function invalidate(Model $model, string $event): void
    {
        if ($this->tagIsExcluded(get_class($model))) {
            return;
        }

        if ($this->dispatchedEvents->alreadyDispatched($event.'-'.$this->makeModelName($model))) {
            return;
        }

        Helpers::debug("MODEL EVENT: {$event} on model ".get_class($model));

        $modelChanged = new ModelChanged($model, $event);

        if ($this->createOrDeleteEvent($event) || $modelChanged->isDirty()) {
            $this->invalidateCDNCache($model, $event);
        }
    }

    public function boot()
    {
        $this->dispatchedEvents = app('a17.edgeflush.dispatchedEvents');
    }

    public function createOrDeleteEvent(string $event): bool
    {
        return in_array($event, ['created', 'deleted']);
    }
}
