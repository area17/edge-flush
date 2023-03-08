<?php declare(strict_types=1);

namespace A17\EdgeFlush\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Contracts\Queue\ShouldQueue;

class EloquentSaved
{
    use CachedOnCDN;

    public function handle(string $event, array $models): void
    {
        foreach ($models as $model) {
            if (filled($model)) {
                $this->invalidateCDNCache($model);
            }
        }
    }
}
