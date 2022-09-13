<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Behaviours\ControlsInvalidations;
use A17\EdgeFlush\Contracts\Service as ServiceContract;

abstract class BaseService implements ServiceContract
{
    use ControlsInvalidations;

    protected bool|null $enabled = null;

    public function addHeadersToResponse(
        Response $response,
        string $service,
        string $tag
    ): Response {
        if (!$this->enabled()) {
            return $response;
        }

        $this->addTagToHeaders($service, $response, $tag);

        $this->addHeadersFromRequest($response);

        return $response;
    }

    public function makeResponse(Response $response): Response
    {
        if (!$this->enabled()) {
            return $response;
        }

        Helpers::debug(
            'CACHABLE-MATRIX: ' .
                json_encode(
                    EdgeFlush::cacheControl()->getCachableMatrix($response),
                ),
        );

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

    public function enabled(): bool
    {
        return $this->enabled ??= config('edge-flush.enabled');
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function getInvalidationPathsForTags(
        Invalidation $invalidation
    ): Collection {
        return $invalidation->paths();
    }

    public function addHeadersFromRequest(Response $response): void
    {
        collect(config('edge-flush.headers.from-request'))->each(function (
            string $header
        ) use ($response) {
            if (filled($value = request()->header($header))) {
                $response->headers->set($header, $value);
            }
        });
    }

    private function addTagToHeaders(
        string $service,
        Response $response,
        string $value
    ): void {
        collect(config("edge-flush.headers.$service"))->each(
            fn(string $header) => $response->headers->set(
                $header,
                collect($value)->join(', '),
            ),
        );
    }
}
