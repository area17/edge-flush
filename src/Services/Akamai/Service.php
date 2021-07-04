<?php

namespace A17\CDN\Services\Akamai;

use A17\CDN\CDN;
use Symfony\Component\HttpFoundation\Response;
use A17\CDN\Services\BaseService;
use A17\CDN\Services\TagsContainer;
use Illuminate\Support\Facades\Http;

class Service extends BaseService
{
    protected $tags;

    public function makeResponse(Response $response): Response
    {
        return $this->addHeadersToResponse(
            $response,
            'tags',
            CDN::tags()->getTagsHash($response),
        );
    }

    private function getApiPath(): string
    {
        return '/ccu/v3/invalidate/tag/production';
    }

    /**
     * @return \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|mixed
     */
    private function getHost()
    {
        return config('services.akamai.host');
    }

    public function getInvalidationURL(): string
    {
        return 'https://' . $this->getHost() . $this->getApiPath();
    }

    public function purge($keys)
    {
        $body = [
            'objects' => collect($keys)
                ->unique()
                ->toArray(),
        ];

        Http::withHeaders([
            'Authorization' => $this->getAuthHeaders($body),
        ])->post($this->getInvalidationURL(), $body);
    }

    public function getAuthHeaders($body): string
    {
        $auth = new AkamaiAuthentication();

        $auth->setHost($this->getHost());

        $auth->setBody(is_string($body) ? $body : json_encode($body));

        $auth->setHttpMethod('POST');

        $auth->setAuth(
            config('services.akamai.client_token'),
            config('services.akamai.client_secret'),
            config('services.akamai.access_token'),
        );

        $auth->setPath($this->getApiPath());

        return $auth->createAuthHeader();
    }
}
