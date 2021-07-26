<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Contracts\Service as ServiceContract;

abstract class BaseService implements ServiceContract
{
    /**
     * @psalm-suppress UndefinedMethod
     */
    public function addHeadersToResponse(
        Response $response,
        string $service,
        string $value
    ): Response {
        collect(config("edge-flush.headers.$service"))->each(
            fn(string $header) => $response->header(
                $header,
                collect($value)->join(', '),
            ),
        );

        return $response;
    }

    public function makeResponse(Response $response): Response
    {
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
}
