<?php

namespace A17\EdgeFlush\Console\Commands;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Console\Command;

class InvalidateAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'edge-flush:invalidate-all';

    /**
     * The console command description.
     *
     * @var null|string
     */
    protected $description = 'Invalidate all pages from CDN cache';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        EdgeFlush::tags()->invalidateAll();

        $this->info('An invalidation was created.');

        return 0;
    }
}
