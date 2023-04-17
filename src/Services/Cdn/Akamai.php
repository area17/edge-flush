<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services\Cdn;

use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Aws\CloudFront\CloudFrontClient;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use A17\EdgeFlush\Services\Invalidation;
use A17\EdgeFlush\Services\CdnBaseService;
use Akamai\Open\EdgeGrid\Authentication as AkamaiAuthentication;

class Akamai extends Base
{
    protected static string $serviceName = 'akamai';

    protected array $tags = [];

    public function instantiate(): void
    {
    }

    protected function getApiPath(): string
    {
        return '/ccu/v3/invalidate/tag/production';
    }

    protected function getHost(): string|null
    {
        return Helpers::configString('edge-flush.services.akamai.host');
    }

    public function getInvalidationURL(): string
    {
        return 'https://' . $this->getHost() . $this->getApiPath();
    }

    public function getAuthHeaders(mixed $body): string
    {
        $auth = new AkamaiAuthentication();

        $auth->setHost($this->getHost());

        $body = is_string($body) ? $body : json_encode($body);

        $auth->setBody($body === false ? '' : $body);

        $auth->setHttpMethod('POST');

        $auth->setAuth(
            (string) $this->getClientToken(),
            (string) $this->getClientSecret(),
            (string) $this->getAccessToken(),
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
        /**
         * Fast purge is supposed to be completed in seconds, so no need to do a request
         * to check if it's completed.
         */
        $url = Url::where('invalidation_id', $invalidationId)->take(1)->first();

        if (blank($url)) {
            return true;
        }

        return $url->was_purged_at->diffInSeconds(now()) > 30;
    }

    public function createInvalidationRequest(Invalidation|array|null $invalidation = null): Invalidation
    {
        $invalidation = parent::createInvalidationRequest($invalidation);

        $urls = $invalidation->urls()
            ->map(function ($item) {
                return $item instanceof Url ? $item->url_hash : $item;
            })
            ->filter()
            ->unique();

        if ($urls->isEmpty()) {
            return $invalidation;
        }

        $body = [
            'objects' => $urls->toArray(),
        ];

        Helpers::debug('[AKAMAI] dispatchin invalidations for ' . $urls->count() . ' urls');

        $response = Http::withHeaders([
            'Authorization' => $this->getAuthHeaders($body),
        ])->post($this->getInvalidationURL(), $body);

        if ($response->failed()) {
            Helpers::error('Error invalidating akamai tags: ' . $response->body());

            $invalidation->setSuccess(false);

            return $invalidation;
        }

        $invalidation->setSuccess(true);

        $invalidationId = $response->json('purgeId');

        if (is_string($invalidationId) || is_numeric($invalidationId)) {
            $invalidation->setId((string) $invalidationId);
        }

        $invalidation->setInvalidationResponse($response->json());

        return $invalidation;
    }

    public function isProperlyConfigured(): bool
    {
        return filled($this->getClientToken())
            && filled($this->getClientSecret())
            && filled($this->getAccessToken());
    }

    public function getClientToken(): string|null
    {
        return Helpers::configString('edge-flush.services.akamai.client_token') ?? null;
    }

    public function getClientSecret(): string|null
    {
        return Helpers::configString('edge-flush.services.akamai.client_secret') ?? null;
    }

    public function getAccessToken(): string|null
    {
        return Helpers::configString('edge-flush.services.akamai.access_token') ?? null;
    }
}
