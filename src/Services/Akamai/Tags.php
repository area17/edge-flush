<?php

namespace A17\CDN\Services\Akamai;

use Illuminate\Http\Response;

class Tags
{
    const CONTAINER_KEY = 'cdn-tags';

    public function __construct()
    {
        $this->instantiateContainer();
    }

    private function instantiateContainer()
    {
        if (!app()->bound(self::CONTAINER_KEY)) {
            app()->singleton(self::CONTAINER_KEY, function () {
                return new TagsContainer();
            });
        }
    }

    public function __call($name, $arguments)
    {
        return app(self::CONTAINER_KEY)->$name(...$arguments);
    }

    public function addHttpHeadersToResponse($response)
    {
        if ($response instanceof Response) {
            $response->header('Edge-Cache-Tag', $this->getTagsHash());

            if (!app()->environment('production')) {
                $response->header('X-Cache-Tag', $this->getTagsHash());
            }
        }

        return $response;
    }
}
