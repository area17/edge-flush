<?php

namespace A17\CDN\Jobs;

use A17\CDN\CDN;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class StoreTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $models;

    public $tag;

    public $url;

    /**
     * Create a new job instance.
     *
     * @param $models
     * @param $tag
     */
    public function __construct($models, $tag, $url)
    {
        $this->models = $models;

        $this->tag = $tag;

        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CDN::tags()->storeCacheTags($this->models, $this->tag, $this->url);
    }
}
