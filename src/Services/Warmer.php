<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Http\Request;
use A17\EdgeFlush\Models\Url;
use GuzzleHttp\Client as Guzzle;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise\Utils as Promise;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;

class Warmer
{
    protected Guzzle|null $guzzle = null;

    public function execute(): void
    {
        if (!$this->enabled()) {
            return;
        }

        $this->warm($this->getColdUrls());
    }

    public function warm(Collection $urls): void
    {
        $count = Helpers::configInt('edge-flush.warmer.concurrent_requests', 10);

        $count = !is_numeric($count) ? 10 : $count;

        Helpers::debug('[WARMER] Warming up ' . $urls->count() . ' URLs using ' . $count . ' concurrent requests');

        while ($urls->count() > 0) {
            $chunk = $urls->splice(0, (int) $count);

            $this->dispatchWarmRequests($chunk);

            EdgeFlush::tags()->markUrlsAsWarmed($chunk);
        }
    }

    public function enabled(): bool
    {
        return EdgeFlush::warmerServiceIsEnabled();
    }

    public function getColdUrls(): Collection
    {
        $max = Helpers::configInt('edge-flush.warmer.max_urls', 100);

        $max = !is_numeric($max) ? 100 : (int) $max;

        return Url::whereNotNull('was_purged_at')
            ->take($max)
            ->orderBy('hits', 'desc')
            ->get()
            ->groupBy('invalidation_id')
            ->filter(fn($group, $invalidationId) => $this->invalidationIsCompleted($invalidationId))
            ->flatten();
    }

    protected function dispatchWarmRequests(Collection $urls): void
    {
        foreach ((array) Helpers::configArray('edge-flush.warmer.types', []) as $type) {
            if ($type === 'internal') {
                $this->dispatchInternalWarmRequests($urls);
            }

            if ($type === 'external') {
                $this->dispatchExternalWarmRequests($urls);
            }
        }
    }

    public function dispatchInternalWarmRequests(Collection $urls): void
    {
        $urls->map(fn($url) => $this->dispatchInternalWarmRequest($url instanceof Url ? $url->url : $url));
    }

    public function dispatchInternalWarmRequest(string $url): void
    {
        $parsed = Helpers::parseUrl($url);

        parse_str($parsed['query'] ?? '', $parameters);

        $request = Request::create($parsed['path'], 'GET', $parameters);

        $this->addHeaders($request, $this->getHeaders($url));

        app()->handle($request);
    }

    public function dispatchExternalWarmRequests(Collection $urls): void
    {
        $startTime = microtime(true);

        /** @var \GuzzleHttp\Promise\PromiseInterface[] $promises */
        $promises = [];

        /** @var Url $url */
        foreach ($urls as $url) {
            Helpers::debug("WARMING: $url->url");

            $promises[$url->url] = $this->getGuzzle()->getAsync($url->url, [
                'headers' => $this->getHeaders($url->url),
            ]);
        }

        $responses = Promise::inspectAll($promises);

        $executionTime = microtime(true) - $startTime;

        Helpers::debug("WARMER-ELAPSED-TIME: {$executionTime}s - URLS: {$urls->count()}");

        (new Collection($responses))->each(function ($response) {
            if ($response['state'] === 'rejected') {
                $context = $response['reason']->getHandlerContext();

                $error = $context['error'] ?? 'missing error';

                $url = $context['url'] ?? 'missing url';

                if ($response['reason'] instanceof GuzzleConnectException) {
                    Helpers::debug("WARMER-ERROR: $error - $url");
                } else {
                    Helpers::debug("WARMER-REJECTED: $error - $url - " . $response['reason']->getResponse()->getBody());
                }
            } else {
                Helpers::debug(
                    "WARMER-SUCCESS : {$response['value']->getStatusCode()} - " .
                        json_encode($response['value']->getHeaders()),
                );
            }
        });
    }

    public function getGuzzle(): Guzzle
    {
        if ($this->guzzle instanceof Guzzle) {
            return $this->guzzle;
        }

        Helpers::debug('WARMER-GUZZLE-CONFIG: ' . json_encode($this->getGuzzleConfiguration()));

        return $this->guzzle = new Guzzle($this->getGuzzleConfiguration());
    }

    public function addHeaders(Request $request, array $headers): void
    {
        (new Collection($headers))->each(fn($value, $key) => $request->headers->set($key, $value));
    }

    public function getHeaders(string $url): array
    {
        $headers =
            [
                'X-Edge-Flush-Warmed-Url' => $url,

                'X-Edge-Flush-Warmed-At' => (string) now(),
            ] + Helpers::configArray('edge-flush.warmer.headers', []);

        if (blank($headers['PHP_AUTH_USER'])) {
            unset($headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']);
        }

        return $headers;
    }

    public function isWarming(Request|null $request = null): bool
    {
        if (!$request instanceof Request) {
            $request = EdgeFlush::getRequest();
        }

        return filled($request->header('X-Edge-Flush-Warmed-Url', null));
    }

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        if (blank($invalidationId)) {
            return true;
        }

        return EdgeFlush::cdn()->invalidationIsCompleted($invalidationId);
    }

    public function getGuzzleConfiguration(): array
    {
        $config =
            [
                'timeout' => Helpers::configInt('edge-flush.warmer.connection_timeout') / 1000, // Guzzle expects seconds

                'connect_timeout' => Helpers::configInt('edge-flush.warmer.connection_timeout', 1000),

                'verify' => Helpers::configBool('edge-flush.warmer.check_ssl_certificate'),

                'curl' =>
                    [
                        CURLOPT_CONNECT_ONLY => Helpers::configBool('edge-flush.warmer.curl.connect_only', false),

                        CURLOPT_NOBODY => !Helpers::configBool('edge-flush.warmer.curl.get_body', true),

                        CURLOPT_ACCEPT_ENCODING => !Helpers::configBool('edge-flush.warmer.curl.compress', true),
                    ] + (array) Helpers::configArray('edge-flush.warmer.curl.extra_options', []),
            ] + (array) Helpers::configArray('edge-flush.warmer.extra_options');

        $username = Helpers::configString('edge-flush.warmer.basic_authentication.username');
        $password = Helpers::configString('edge-flush.warmer.basic_authentication.password');

        if (filled($username) and filled($password)) {
            $config['auth'] = [$username, $password];
        }

        return $config;
    }

    public function boot(): self
    {
        return $this;
    }
}
