<?php declare(strict_types=1);

namespace A17\EdgeFlush\Listeners;

use A17\EdgeFlush\Services\Entity;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Support\Facades\Event;
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
        Event::listen('eloquent.builder.*', function ($event, $model) {
            $model = $this->createModelInstance(json_decode($model[0], true));

            $this->invalidate($model, 'builder.updating');
        });

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

    public function updating(Model $model): void
    {
        $this->invalidate($model, 'updating');
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

    public function pivotSynced(mixed $observed, Model $model, string $relationName, array $changes): void
    {
        $this->invalidate($model, 'pivot-synced', ['name' => $relationName, 'changes' => $changes]);
    }

    public function invalidate(Model $model, string $event, array $relation = []): void
    {
        if ($this->tagIsExcluded(get_class($model))) {
            return;
        }

        if (blank($this->dispatchedEvents)) {
            return;
        }

        $dispatched = $this->dispatchedEvents->alreadyDispatched($event.'-'.$this->makeModelName($model));

        if (filled($dispatched) && $dispatched !== false) {
            return;
        }

        $entity = new Entity($model, $event);

        $entity->setRelation($relation);

        if ($entity->mustInvalidate()) {
            $this->invalidateCDNCache($entity);
        }
    }

    public function boot(): void
    {
        $this->dispatchedEvents = app('a17.edgeflush.dispatchedEvents');
    }
    
    public function createModelInstance(array $model): Model
    {
        $newModel = new $model['model']();

        $newModel->setRawAttributes($model['attributes']);

        $updates = collect($model['updates'])->mapWithKeys(function ($value, $key) {
            return [$key => 'just-to-trigger-dirty-attributes'];
        })->toArray();

        $newModel->setOriginalAttributes(array_merge($model['attributes'], $updates));

        return $newModel;
    }
}
