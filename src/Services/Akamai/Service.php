<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services\Akamai;

use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Facades\Http;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use A17\EdgeFlush\Services\Invalidation;
use Akamai\Open\EdgeGrid\Authentication as AkamaiAuthentication;

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
        return Helpers::configString('edge-flush.services.akamai.host');
    }

    public function getInvalidationURL(): string
    {
        return 'https://' . $this->getHost() . $this->getApiPath();
    }

    public function invalidate(Invalidation $invalidation): Invalidation
    {
        // TODO: must be redone
        //        if (!$this->enabled()) {
        //            return $this->unsuccessfulInvalidation();
        //        }
        //
        //        $body = [
        //            'objects' => collect($invalidation->tags())
        //                ->map(function ($item) {
        //                    return $item instanceof Tag ? $item->tag : $item;
        //                })
        //                ->unique()
        //                ->toArray(),
        //        ];
        //
        //        Http::withHeaders([
        //            'Authorization' => $this->getAuthHeaders($body),
        //        ])->post($this->getInvalidationURL(), $body);
        //
        //        return $this->successfulInvalidation();

        return $invalidation;
    }

    public function invalidateAll(): Invalidation
    {
        if (!$this->enabled()) {
            return $this->unsuccessfulInvalidation();
        }

        return $this->invalidate(
            $this->createInvalidation(
                Helpers::configArray(
                    'edge-flush.services.akamai.invalidate_all_paths',
                ),
            ),
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
            Helpers::configString('edge-flush.services.akamai.client_token') ??
                '',
            Helpers::configString('edge-flush.services.akamai.client_secret') ??
                '',
            Helpers::configString('edge-flush.services.akamai.access_token') ??
                '',
        );

        $auth->setPath($this->getApiPath());

        return $auth->createAuthHeader();
    }

    public function maxUrls(): int
    {
        return Helpers::configInt('edge-flush.services.akamai.max_urls') ?? 300;
    }

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        return false;
    }
}
