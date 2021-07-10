<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use Symfony\Component\HttpFoundation\Response;
use A17\CDN\Contracts\Service as ServiceContract;

abstract class BaseService implements ServiceContract
{
    public function addHeadersToResponse($response, $service, $value): Response
    {
        if (!$response instanceof Response) {
            return $response;
        }

        collect(config("cdn.headers.$service"))->each(
            fn($header) => $response->header(
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
}
