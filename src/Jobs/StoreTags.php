<?php declare(strict_types=1);

namespace A17\EdgeFlush\Jobs;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class StoreTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Collection $models;

    public array $tags;

    public string $url;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection $models, array $tags, string $url)
    {
        $this->models = $models;

        $this->tags = $tags;

        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        EdgeFlush::tags()->storeCacheTags($this->models, $this->tags, $this->url);
    }
}
