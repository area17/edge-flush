<?php declare(strict_types=1);

namespace A17\EdgeFlush\Listeners;

use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Contracts\Queue\ShouldQueue;

class EloquentBooted
{
    use CachedOnCDN;

    public function handle(string $event, array $models): void
    {
        foreach ($models as $model) {
            if ($model instanceof Model) {
                $model::observe(EloquentObserver::class);
            }
        }
    }
}
