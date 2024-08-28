<?php

namespace A17\EdgeFlush\Console\Commands;

use Illuminate\Support\Str;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Url;
use Illuminate\Console\Command;

class Urls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'edge-flush:urls';

    /**
     * The console command description.
     *
     * @var null|string
     */
    protected $description = 'List all URLs that are on EdgeFlush.';

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
        $urls = Url::select('url')->get()->pluck('url');

        $this->comment('Listing '.$urls->count().' URLs from the EdgeFlush tables...');

        $urls = $urls->map(fn($url) => $this->replaceDomain($url))->unique()->sort()->each(fn($url) => $this->info($url));

        return 0;
    }

    protected function replaceDomain(mixed $url): string
    {
        $newDomain = config('app.url');

        if (!is_string($url) || !is_string($newDomain)) {
            return '';
        }

        $oldDomain = parse_url($url, PHP_URL_SCHEME) . '://'. parse_url($url, PHP_URL_HOST);

        $url = str_replace($oldDomain, $newDomain, $url);

        if (Str::endsWith($url, '/')) {
            $url = Str::beforeLast($url, '/');
        }

        return $url;
    }
}
