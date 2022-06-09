<?php

namespace A17\EdgeFlush\Services\CloudFront;

use Aws\AwsClient;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Aws\CloudFront\CloudFrontClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class Service extends BaseService implements CDNService
{
    protected CloudFrontClient $client;

    public function __construct()
    {
        if ($this->enabled()) {
            $this->instantiate();
        }
    }

    public function invalidate(Collection $tags): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return $this->mustInvalidateAll($tags)
            ? $this->invalidateAll()
            : $this->invalidatePaths($tags);
    }

    public function invalidateAll(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return $this->createInvalidationRequest(
            config('edge-flush.services.cloud_front.invalidate_all_paths'),
        );
    }

    protected function getDistributionId(): string|null
    {
        return config('edge-flush.services.cloud_front.distribution_id');
    }

    public function getClient(): CloudFrontClient|null
    {
        if (!config('edge-flush.enabled')) {
            return null;
        }

        return new CloudFrontClient([
            'region' => config('edge-flush.services.cloud_front.region'),

            'version' => config('edge-flush.services.cloud_front.sdk_version'),

            'credentials' => [
                'key' => config('edge-flush.services.cloud_front.key'),
                'secret' => config('edge-flush.services.cloud_front.secret'),
            ],
        ]);
    }

    protected function hasInProgressInvalidation(): bool
    {
        $list = $this->client
            ->listInvalidations([
                'DistributionId' => $this->getDistributionId(),
            ])
            ->get('InvalidationList');

        if (isset($list['Items']) && !empty($list['Items'])) {
            return collect($list['Items'])
                ->where('Status', 'InProgress')
                ->count() > 0;
        }

        return false;
    }

    protected function createInvalidationRequest(array $paths): bool
    {
        $paths = array_filter($paths);

        if (count($paths) === 0) {
            return false;
        }

        try {
            $result = $this->client->createInvalidation([
                'DistributionId' => $this->getDistributionId(),
                'InvalidationBatch' => [
                    'Paths' => [
                        'Quantity' => count($paths),
                        'Items' => $paths,
                    ],
                    'CallerReference' => time(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error(
                'CDN: CloudFront invalidation request failed: ' .
                    $e->getMessage() .
                    ' - PATHS: ' .
                    json_encode($paths),
            );

            return false;
        }

        return filled($result);
    }

    protected function instantiate(): void
    {
        $client = static::getClient();

        if ($client instanceof CloudFrontClient) {
            $this->client = $client;
        }
    }

    protected function getInvalidationPath(mixed $item): string|null
    {
        if (is_string($item)) {
            return $item;
        }

        $url = $item instanceof Url ? $item->url : $item->url->url ?? $item;

        if (!is_string($url)) {
            return null;
        }

        return Helpers::parseUrl($url)['path'] ?? '/*';
    }

    public function getInvalidationPathsForTags(Collection $tags): Collection
    {
        return collect($tags)->mapWithKeys(
            fn($tag) => [$this->getInvalidationPath($tag) => $tag],
        );
    }

    public function mustInvalidateAll(Collection $tags): bool
    {
        return $this->getInvalidationPathsForTags($tags)->count() >
            $this->maxUrls();
    }

    public function invalidatePaths(Collection $tags): bool
    {
        return $this->createInvalidationRequest(
            $this->getInvalidationPathsForTags($tags)
                ->keys()
                ->toArray(),
        );
    }

    public function maxUrls(): int
    {
        return config('edge-flush.services.cloud_front.max_urls');
    }
}
