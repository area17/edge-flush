<?php declare(strict_types=1);

namespace A17\EdgeFlush\Listeners;

use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class EloquentSaved
{
    use CachedOnCDN;

    public function handle(string $event, array $models): void
    {
        foreach ($models as $model) {
            $this->invalidateCDNCache($model);
        }
    }
}
