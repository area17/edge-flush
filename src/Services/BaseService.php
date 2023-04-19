<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\CastObject;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Behaviours\ControlsInvalidations;

abstract class BaseService
{
    use ControlsInvalidations, CastObject;

    protected bool|null $enabled = null;

    public function addHeadersToResponse(Response $response, string $service, string $tag): Response
    {
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

        $this->logCachableMatrix($response);

        return $this->addHeadersToResponse(
            $response,
            'tags',
            (string) EdgeFlush::tags()->getTagsHash($response, EdgeFlush::getRequest()),
        );
    }

    public function matchAny(string $string, array $patterns): bool
    {
        return !!(new Collection($patterns))->reduce(
            fn($matched, $pattern) => $matched || $this->match($pattern, $string),
            false,
        );
    }

    public function match(string $patten, string $string): bool
    {
        if ($patten === $string) {
            return true;
        }

        if ($patten[0] === '|' && $patten[strlen($patten) - 1] === '|') {
            preg_match($patten, $string, $matches);

            return count($matches) > 0;
        }

        $patten = str_replace('\\', '_', $patten);

        $string = str_replace('\\', '_', $string);

        return fnmatch($patten, $string);
    }

    public function enabled(): bool
    {
        return $this->enabled ??= Helpers::configBool('edge-flush.enabled.package');
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function getInvalidationPathsForTags(Invalidation $invalidation): Collection
    {
        return $invalidation->paths();
    }

    public function addHeadersFromRequest(Response $response): void
    {
        (new Collection(Helpers::configArray('edge-flush.headers.from-request')))->each(function (string $header) use (
            $response
        ) {
            if (filled($value = request()->header($header))) {
                $response->headers->set($header, $value);
            }
        });
    }

    private function addTagToHeaders(string $service, Response $response, string $value): void
    {
        (new Collection(Helpers::configArray("edge-flush.headers.$service")))->each(
            fn(string $header) => $response->headers->set($header, (new Collection([$value]))->join(', ')),
        );
    }

    protected function logCachableMatrix(Response $response): void
    {
        if (!Helpers::configBool('edge-flush.debug')) {
            return;
        }

        $matrix = json_encode(EdgeFlush::cacheControl()->getCachableMatrix($response));

        Helpers::debug('CACHABLE-MATRIX: ' . $matrix);

        $response->headers->set('X-EDGE-FLUSH-CACHABLE-MATRIX', $matrix);
    }
}
