<?php

namespace A17\EdgeFlush\Console\Commands;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use Illuminate\Console\Command;

class Zap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'edge-flush:zap {--force : This operation can only be executed if forced}';

    /**
     * The console command description.
     *
     * @var null|string
     */
    protected $description = 'Remove all records from the tags and urls tables to start fresh.';

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
        if (!$this->option('force')) {
            $this->error('This operation can only be executed if forced.');

            return 1;
        }

        Url::truncate();

        Tag::truncate();

        $this->info('Zapped.');

        return 0;
    }
}
