<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Env;
use Faker\Extension\Helper;
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
            ->take(config('edge-flush.warmer.max_urls'))
            ->orderBy('hits', 'desc')
            ->get()
            ->groupBy('invalidation_id')
            ->filter(
                fn($group, $invalidationId) => $this->invalidationIsCompleted(
                    $invalidationId,
                ),
            )
            ->flatten();
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

        $this->addHeaders($request, $this->getHeaders($url));

        app()->handle($request);
    }

    public function dispatchExternalWarmRequests($urls)
    {
        $startTime = microtime(true);

        $responses = Promise::inspectAll(
            $urls->map(function ($url) {
                Helpers::debug("WARMING: $url->url");

                return $this->getGuzzle()->getAsync($url->url, [
                    'headers' => $this->getHeaders($url->url),
                ]);
            }),
        );

        $executionTime = microtime(true) - $startTime;

        Helpers::debug(
            "WARMER-ELAPSED-TIME: {$executionTime}s - URLS: {$urls->count()}",
        );

        collect($responses)->each(function ($response) {
            if ($response['state'] === 'rejected') {
                $context = $response['reason']->getHandlerContext();

                $error = $context['error'] ?? 'missing error';

                $url = $context['url'] ?? 'missing url';

                Helpers::debug(
                    "WARMER-REJECTED: $error - $url - " .
                        $response['reason']->getResponse()->getBody(),
                );
            } else {
                Helpers::debug(
                    "WARMER-SUCCESS : {$response['value']->getStatusCode()} - " .
                        json_encode($response['value']->getHeaders()),
                );
            }
        });
    }

    public function getGuzzle()
    {
        if (filled($this->guzzle)) {
            return $this->guzzle;
        }

        Helpers::debug(
            'WARMER-GUZZLE-CONFIG: ' .
                json_encode($this->getGuzzleConfiguration()),
        );

        return $this->guzzle = new Guzzle($this->getGuzzleConfiguration());
    }

    public function addHeaders($request, $headers)
    {
        collect($headers)->each(
            fn($value, $key) => $request->headers->set($key, $value),
        );
    }

    public function getHeaders($url): array
    {
        return [
            'X-Edge-Flush-Warming-Url' => $url,

            'X-Edge-Flush-Warmed-At' => (string) now(),
        ] + config('edge-flush.warmer.headers', []);
    }

    public function isWarming(Request|null $request = null)
    {
        if (blank($request)) {
            $request = EdgeFlush::getRequest();
        }

        return filled($request->header('X-Edge-Flush-Warming-Url', null));
    }

    public function invalidationIsCompleted($invalidationId)
    {
        return EdgeFlush::cdn()->invalidationIsCompleted($invalidationId);
    }

    public function getGuzzleConfiguration()
    {
        return [
            'timeout' => config('edge-flush.warmer.connection_timeout') / 1000, // Guzzle expects seconds

            'connect_timeout' => config('edge-flush.warmer.connection_timeout'),

            'verify' => config('edge-flush.warmer.check_ssl_certificate'),

            'auth' => [
                config('edge-flush.warmer.basic_authentication.username'),
                config('edge-flush.warmer.basic_authentication.password'),
            ],

            'curl' =>
                [
                    CURLOPT_CONNECT_ONLY => config(
                        'edge-flush.warmer.curl.connect_only',
                        false,
                    ),

                    CURLOPT_NOBODY => !config(
                        'edge-flush.warmer.curl.get_body',
                        true,
                    ),

                    CURLOPT_ACCEPT_ENCODING => !config(
                        'edge-flush.warmer.curl.compress',
                        true,
                    ),
                ] + (array) config('edge-flush.warmer.curl.extra_options', []),
        ] + (array) config('edge-flush.warmer.extra_options');
    }
}
