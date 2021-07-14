<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use A17\CDN\Models\Tag;
use A17\CDN\Models\Url;
use GuzzleHttp\Client as Guzzle;
use SebastianBergmann\Timer\Timer;
use GuzzleHttp\Promise\Utils as Promise;

class Warmer
{
    protected $guzzle;

    public function __construct()
    {
        $this->guzzle = new Guzzle([
            'timeout' => config('cdn.warmer.connection_timeout'),
            'connect_timeout' => config('cdn.warmer.connection_timeout'),
        ]);
    }

    public function execute()
    {
        if (!$this->enabled()) {
            return;
        }

        $this->warm($this->getColdUrls());
    }

    public function warm($urls)
    {
        while ($urls->count() > 0) {
            $chunk = $urls->splice(
                0,
                config('cdn.warmer.concurrent_requests'),
            );

            $this->dispatchWarmRequests($chunk);

            $this->resetWarmStatus($chunk);
        }
    }

    public function enabled()
    {
        return CDN::enabled() && config('cdn.warmer.enabled');
    }

    public function getColdUrls()
    {
        return Url::whereNotNull('was_purged_at')
            ->where(
                'was_purged_at',
                '<',
                now()->subMillis(config('cdn.warmer.wait_before_warming')),
            )
            ->take(config('cdn.warmer.max_urls'))
            ->orderBy('hits', 'desc')
            ->get();
    }

    protected function dispatchWarmRequests($urls)
    {
        Promise::inspectAll(
            $urls->map(fn($url) => $this->guzzle->getAsync($url->url)),
        );
    }

    protected function resetWarmStatus($urls)
    {
        Url::whereIn('id', $urls->pluck('id')->toArray())->update([
            'was_purged_at' => null,
        ]);
    }
}
