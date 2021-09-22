<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use A17\EdgeFlush\EdgeFlush;
use Illuminate\Http\Request;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use Illuminate\Routing\Route;
use GuzzleHttp\Client as Guzzle;
use SebastianBergmann\Timer\Timer;
use GuzzleHttp\Promise\Utils as Promise;

class Warmer
{
    protected $guzzle;

    public function __construct()
    {
        $this->guzzle = new Guzzle([
            'timeout' => config('edge-flush.warmer.connection_timeout') / 1000, // Guzzle expects seconds
            'connect_timeout' => config('edge-flush.warmer.connection_timeout'),
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
                config('edge-flush.warmer.concurrent_requests'),
            );

            $this->dispatchWarmRequests($chunk);

            $this->resetWarmStatus($chunk);
        }
    }

    public function enabled()
    {
        return EdgeFlush::enabled() && config('edge-flush.warmer.enabled');
    }

    public function getColdUrls()
    {
        return Url::whereNotNull('was_purged_at')
            ->where(
                'was_purged_at',
                '<',
                now()->subMillis(
                    config('edge-flush.warmer.wait_before_warming'),
                ),
            )
            ->take(config('edge-flush.warmer.max_urls'))
            ->orderBy('hits', 'desc')
            ->get();
    }

    protected function dispatchWarmRequests($urls)
    {
        foreach (config('edge-flush.warmer.types') as $type) {
            if ($type === 'internal') {
                $this->dispatchInternalWarmRequests($urls);
            }

            if ($type === 'external') {
                $this->dispatchExternalWarmRequests($urls);
            }
        }
    }

    protected function resetWarmStatus($urls)
    {
        Url::whereIn('id', $urls->pluck('id')->toArray())->update([
            'was_purged_at' => null,
        ]);
    }

    public function dispatchInternalWarmRequests($urls)
    {
        $urls->map(fn($url) => $this->dispatchInternalWarmRequest($url));
    }

    public function dispatchInternalWarmRequest($fullUrl)
    {
        parse_str(parse_url($fullUrl)['query'] ?? '', $parameters);

        Route::dispatch(
            Request::create(Str::before($url, '?'), 'GET', $parameters),
        );
    }

    public function dispatchExternalWarmRequests($urls)
    {
        Promise::inspectAll(
            $urls->map(function ($url) {
                return $this->guzzle->getAsync($url->url);
            }),
        );
    }
}
