<?php

namespace A17\EdgeFlush\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Exceptions\PackageException;
use A17\EdgeFlush\Support\Facades\EdgeFlush;

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
     * @var string
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
            (new Collection(config('edge-flush.package.sections')))
                ->sort()
                ->map(fn($value) => [$value, "php artisan edge-flush:config:merge {$value}"])
                ->toArray(),
        );

        return Command::SUCCESS;
    }
}
