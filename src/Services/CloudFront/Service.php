<?php

namespace App\Services\CloudFront;

use A17\CDN\CDN;
use A17\CDN\Services\BaseService;
use A17\CDN\Contracts\CDNService;
use Illuminate\Support\Facades\Log;
use Aws\CloudFront\CloudFrontClient;
use Symfony\Component\HttpFoundation\Response;

class Service extends BaseService implements CDNService
{
    protected $client = null;

    public function __construct()
    {
        $this->instantiate();
    }

    public function makeResponse(Response $response): Response
    {
        return $response;
    }

    public function purge($tags)
    {
        return $this->createInvalidationRequest($tags);
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

    private function getDistributionId()
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

    public function invalidate()
    {
        if ($this->isEnabled()) {
            $this->dispatchInvalidation();
        }
    }

    private function hasInProgressInvalidation(): bool
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

    private function createInvalidationRequest($paths = [])
    {
        if (is_object($this->client) && count($paths) > 0) {
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

    /**
     * @return \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|mixed
     */
    public function isEnabled()
    {
        return config('cdn.services.cloud_front.enabled', false);
    }

    private function instantiate(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (is_object($client = static::getClient())) {
            $this->client = $client;
        }
    }
}
