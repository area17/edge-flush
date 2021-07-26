<?php

namespace A17\EdgeFlush\Services\CloudFront;

use Aws\AwsClient;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
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
        $this->instantiate();
    }

    public function invalidate(Collection $items): bool
    {
        $items = collect($items)
            ->map(fn($item) => $this->getInvalidationPath($item))
            ->unique()
            ->toArray();

        return $this->createInvalidationRequest($items);
    }

    public function invalidateAll(): bool
    {
        return $this->createInvalidationRequest(
            config('edge-flush.services.cloud_front.invalidate_all_paths'),
        );
    }

    protected function getDistributionId(): ?string
    {
        return config('edge-flush.services.cloud_front.distribution_id');
    }

    public function getClient(): CloudFrontClient
    {
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

    protected function createInvalidationRequest(array $paths = []): bool
    {
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
                'EdgeFlush: CloudFront invalidation request failed: ' .
                    $e->getMessage(),
            );

            return false;
        }

        return filled($result);
    }

    protected function instantiate(): void
    {
        $this->client = static::getClient();
    }

    public function getInvalidationPath($item)
    {
        $url = $item instanceof Url ? $item->url : $item->url->url ?? null;

        if (!is_string($url)) {
            return null;
        }

        $url = parse_url($url);

        return $url['path'] ?? '/';
    }
}
