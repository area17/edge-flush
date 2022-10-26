<?php declare(strict_types=1);

namespace A17\EdgeFlush\Jobs;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use A17\EdgeFlush\Services\Invalidation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InvalidateTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Invalidation|null $invalidation = null;

    /**
     * Create a new job instance.
     */
    public function __construct(Invalidation|null $invalidation = null)
    {
        $this->invalidation = $invalidation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        EdgeFlush::tags()->invalidateTags(
            $this->invalidation ?? new Invalidation(),
        );
    }
}
