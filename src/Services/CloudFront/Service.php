<?php

namespace A17\CDN\Services\CloudFront;

use A17\CDN\CDN;
use Aws\AwsClient;
use A17\CDN\Services\BaseService;
use A17\CDN\Contracts\CDNService;
use Illuminate\Support\Facades\Log;
use Aws\CloudFront\CloudFrontClient;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class Service extends BaseService implements CDNService
{
    protected CloudFrontClient $client;

    public function __construct()
    {
        $this->instantiate();
    }

    public function invalidate(array $items): bool
    {
        $items = collect($items)
            ->map(fn($item) => $item instanceof Model ? $item->url : $item)
            ->unique()
            ->toArray();

        return $this->createInvalidationRequest($items);
    }

    protected function dispatchInvalidation(): void
    {
        if (!$this->hasInProgressInvalidation()) {
            if (!$this->createInvalidationRequest(['/*'])) {
                Log::debug('Cloudfront invalidation request failed');
            }
        } else {
            Log::debug(
                'Cloudfront : there are already some invalidations in progress',
            );
        }
    }

    protected function getDistributionId(): ?string
    {
        return config('cdn.services.cloud_front.distribution_id');
    }

    public function getClient(): CloudFrontClient
    {
        return new CloudFrontClient([
            'region' => config('cdn.services.region'),

            'version' => config('cdn.services.cloud_front.sdk_version'),

            'credentials' => [
                'key' => config('cdn.services.cloud_front.key'),
                'secret' => config('cdn.services.cloud_front.secret'),
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

    /**
     * @param array $paths
     * @return mixed
     */
    protected function createInvalidationRequest(array $paths = [])
    {
        if (count($paths) > 0) {
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
                Log::debug('Cloudfront invalidation request failed');

                return false;
            }

            return $result;
        }
    }

    public function isEnabled(): bool
    {
        return config('cdn.services.cloud_front.enabled', false);
    }

    protected function instantiate(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->client = static::getClient();
    }
}
