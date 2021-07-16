<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use Symfony\Component\HttpFoundation\Response;
use A17\CDN\Contracts\Service as ServiceContract;

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
        collect(config("cdn.headers.$service"))->each(
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
            CDN::tags()->getTagsHash($response),
        );
    }

    public function match(string $patten, string $string): bool
    {
        $patten = str_replace('\\', '_', $patten);

        $string = str_replace('\\', '_', $string);

        return fnmatch($patten, $string);
    }
}
