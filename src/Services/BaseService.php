<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Contracts\Service as ServiceContract;

abstract class BaseService implements ServiceContract
{
    protected $enabled;

    public function addHeadersToResponse(
        Response $response,
        string $service,
        string $value
    ): Response {
        if (!$this->enabled()) {
            return $response;
        }

        collect(config("edge-flush.headers.$service"))->map(
            fn(string $header) => $response->headers->set(
                $header,
                collect($value)->join(', '),
            ),
        );

        return $response;
    }

    public function makeResponse(Response $response): Response
    {
        if (!$this->enabled()) {
            return $response;
        }

        Helpers::debug('CACHE-CONTROL-MATRIX: '. json_encode(EdgeFlush::cacheControl()->getCachableMatrix()));

        return $this->addHeadersToResponse(
            $response,
            'tags',
            EdgeFlush::tags()->getTagsHash($response, EdgeFlush::getRequest()),
        );
    }

    public function matchAny(string $string, array $patterns): bool
    {
        return collect($patterns)->reduce(
            fn($matched, $pattern) => $matched ||
                $this->match($pattern, $string),
            false,
        );
    }

    public function match(string $patten, string $string): bool
    {
        $patten = str_replace('\\', '_', $patten);

        $string = str_replace('\\', '_', $string);

        return fnmatch($patten, $string);
    }

    public function enabled()
    {
        return $this->enabled ??= config('edge-flush.enabled');
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function getInvalidationPathsForTags(Collection $tags): Collection
    {
        return $tags;
    }
}
