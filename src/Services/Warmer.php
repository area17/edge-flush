<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Env;
use A17\EdgeFlush\EdgeFlush;
use Illuminate\Http\Request;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use GuzzleHttp\Client as Guzzle;
use SebastianBergmann\Timer\Timer;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Facades\Route;
use GuzzleHttp\Promise\Utils as Promise;

class Warmer
{
    protected $guzzle;

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
        foreach (config('edge-flush.warmer.types', []) as $type) {
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
        $urls->map(fn($url) => $this->dispatchInternalWarmRequest($url->url));
    }

    public function dispatchInternalWarmRequest($url)
    {
        $parsed = Helpers::parseUrl($url);

        parse_str($parsed['query'] ?? '', $parameters);

        $request = Request::create($parsed['path'], 'GET', $parameters);

        $request->headers->set('X-EDGE-FLUSH-WARMING-URL', $url);

        $this->addHeaders($request, config('edge-flush.warmer.headers'));

        app()->handle($request);
    }

    public function dispatchExternalWarmRequests($urls)
    {
        $responses = Promise::inspectAll(
            $urls->map(function ($url) {
                Helpers::debug("Warming $url...");

                return $this->getGuzzle()->getAsync($url->url);
            }),
        );

        collect($responses)->each(function ($response) {
            if ($response['state'] === 'rejected')
            {
                $context = $response['reason']->getHandlerContext();

                Helpers::debug("WARMER REJECTED: {$context['error']} - {$context['url']}");
            }
        });
    }

    public function getGuzzle()
    {
        if (filled($this->guzzle)) {
            return $this->guzzle;
        }

        return $this->guzzle = new Guzzle([
            'headers' => config('edge-flush.warmer.headers'),
            'timeout' => config('edge-flush.warmer.connection_timeout') / 1000, // Guzzle expects seconds
            'connect_timeout' => config('edge-flush.warmer.connection_timeout'),
            'verify' => config('edge-flush.warmer.check_ssl_certificate'),
            'auth' => [
                config('edge-flush.warmer.basic_authentication.username'),
                config('edge-flush.warmer.basic_authentication.password')
            ],
        ] + (array) config('edge-flush.warmer.extra_options'));
    }

    public function addHeaders($request, $headers)
    {
        collect($headers)->each(
            fn($value, $key) => $request->headers->set($key, $value),
        );
    }
}
