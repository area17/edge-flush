<?php

namespace A17\EdgeFlush\Services\Akamai;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Services\Tags;
use A17\EdgeFlush\Services\Warmer;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Services\CacheControl;
use A17\EdgeFlush\Services\TagsContainer;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;
use Akamai\Open\EdgeGrid\Authentication as AkamaiAuthentication;
use A17\EdgeFlush\Services\ResponseCache\Service as ResponseCache;

class Service extends BaseService implements CDNService
{
    protected array $tags = [];

    protected function getApiPath(): string
    {
        return '/ccu/v3/invalidate/tag/production';
    }

    /**
     * @return string|null
     */
    protected function getHost(): string|null
    {
        return config('edge-flush.services.akamai.host');
    }

    public function getInvalidationURL(): string
    {
        return 'https://' . $this->getHost() . $this->getApiPath();
    }

    public function invalidate(Collection $items): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $body = [
            'objects' => collect($items)
                ->map(function ($item) {
                    return $item instanceof Tag ? $item->tag : $item;
                })
                ->unique()
                ->toArray(),
        ];

        Http::withHeaders([
            'Authorization' => $this->getAuthHeaders($body),
        ])->post($this->getInvalidationURL(), $body);

        return true;
    }

    public function invalidateAll(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return $this->invalidate(
            config('edge-flush.services.akamai.invalidate_all_paths'),
        );
    }

    /**
     * @param mixed $body
     * @return string
     */
    public function getAuthHeaders($body): string
    {
        $auth = new AkamaiAuthentication();

        $auth->setHost($this->getHost());

        $body = is_string($body) ? $body : json_encode($body);

        $auth->setBody($body === false ? '' : $body);

        $auth->setHttpMethod('POST');

        $auth->setAuth(
            config('edge-flush.services.akamai.client_token'),
            config('edge-flush.services.akamai.client_secret'),
            config('edge-flush.services.akamai.access_token'),
        );

        $auth->setPath($this->getApiPath());

        return $auth->createAuthHeader();
    }

    public function maxUrls(): int
    {
        return config('edge-flush.services.akamai.max_urls');
    }
}
