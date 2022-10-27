<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
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
        return (bool) collect($patterns)->reduce(
            fn($matched, $pattern) => $matched ||
                $this->match($pattern, $string),
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
        return $this->enabled ??= Helpers::configBool('edge-flush.enabled');
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
        collect(Helpers::configArray('edge-flush.headers.from-request'))->each(
            function (string $header) use ($response) {
                if (filled($value = request()->header($header))) {
                    $response->headers->set($header, $value);
                }
            },
        );
    }

    private function addTagToHeaders(
        string $service,
        Response $response,
        string $value
    ): void {
        collect(Helpers::configArray("edge-flush.headers.$service"))->each(
            fn(string $header) => $response->headers->set(
                $header,
                collect($value)->join(', '),
            ),
        );
    }

    public function createInvalidation(
        Invalidation|array $invalidation = null
    ): Invalidation {
        $invalidation ??= new Invalidation();

        if (is_array($invalidation)) {
            $paths = [];
            $tags = [];

            foreach ($invalidation as $value) {
                if ($value instanceof Tag) {
                    $tags[] = $value;
                } else {
                    $paths[] = $value;
                }
            }

            $invalidation = new Invalidation();

            $invalidation->setPaths(collect($paths));
            $invalidation->setTags(collect($tags));
        }

        return $invalidation;
    }
}
