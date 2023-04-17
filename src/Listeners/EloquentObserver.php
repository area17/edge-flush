<?php declare(strict_types=1);

namespace A17\EdgeFlush\Listeners;

use A17\EdgeFlush\Services\Entity;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Contracts\Queue\ShouldQueue;
use A17\EdgeFlush\Services\DispatchedEvents;

class EloquentObserver
{
    use CachedOnCDN, MakeTag;

    protected DispatchedEvents|null $dispatchedEvents;

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

    public function pivotSynced(string $observed, Model $model, string $relationName, array $changes): void
    {
        $this->invalidate($model, 'pivot-synced', ['name' => $relationName, 'changes' => $changes]);
    }

    public function invalidate(Model $model, string $event, array $relation = []): void
    {
        if ($this->tagIsExcluded(get_class($model)) || blank($this->dispatchedEvents)) {
            return;
        }

        if ($this->dispatchedEvents->alreadyDispatched($event.'-'.$this->makeModelName($model))) {
            return;
        }

        $entity = new Entity($model, $event);

        $entity->setRelation($relation);

        Helpers::debug(
            "MODEL EVENT: {$event} on model ".$entity->modelName.
            (($relation['name'] ?? null) ? " on relation {$relation['name']}" : '')
        );

        if ($entity->mustInvalidate()) {
            $this->invalidateCDNCache($entity);
        }
    }

    public function boot(): void
    {
        $this->dispatchedEvents = app('a17.edgeflush.dispatchedEvents');
    }
}
