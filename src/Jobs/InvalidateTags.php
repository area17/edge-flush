<?php

namespace A17\CDN\Jobs;

use A17\CDN\CDN;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InvalidateTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $tags;

    /**
     * Create a new job instance.
     *
     * @param $tags
     */
    public function __construct(array $tags = null)
    {
        $this->tags = $tags;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CDN::tags()->invalidateCacheTags($this->tags);
    }
}
