<?php

namespace A17\EdgeFlush\Jobs;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InvalidateTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $model;

    /**
     * Create a new job instance.
     *
     * @param array|null $model
     */
    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            filled($this->model)
                ? EdgeFlush::tags()->invalidateTagsForModel($this->model)
                : EdgeFlush::tags()->invalidateTags();
        } catch (\Throwable) {
            /**
             * If an error happens during an invalidation on the CDN service,
             * we release the job back to the queue
             */
            $this->release();
        }
    }
}
