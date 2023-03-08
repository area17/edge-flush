<?php

namespace A17\EdgeFlush\Console\Commands;

use A17\EdgeFlush\Support\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\EdgeFlush;
use A17\EdgeFlush\Exceptions\PackageException;

class ConfigListSections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'edge-flush:config:list-sections';

    /**
     * The console command description.
     *
     * @var null|string
     */
    protected $description = 'List all sections that can be merged into the published config file';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->table(
            ['Section', 'Merge command'],
            Helpers::collect(config('edge-flush.package.sections'))
                ->sort()
                ->map(fn($value) => is_string($value) ? [$value, "php artisan edge-flush:config:merge {$value}"] : null)
                ->filter()
                ->toArray(),
        );

        return Command::SUCCESS;
    }
}
