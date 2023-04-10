<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services\CloudFront;

use Aws\AwsClient;
use Aws\Result as AwsResult;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Aws\CloudFront\CloudFrontClient;
use Illuminate\Support\Facades\Http;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Services\Invalidation;
use A17\EdgeFlush\Services\CdnBaseService;
use Symfony\Component\HttpFoundation\Response;

class Service extends CdnBaseService
{
    protected static string $serviceName = 'cloud_front';
    
    protected CloudFrontClient $client;

    protected function instantiate(): void
    {
        $client = static::getClient();

        if ($client instanceof CloudFrontClient) {
            $this->client = $client;
        }
    }

    protected function getDistributionId(): string|null
    {
        return Helpers::configString('edge-flush.services.'.$this->serviceId.'.distribution_id');
    }

    public function getClient(): CloudFrontClient|null
    {
        $config = [
            'region' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.region'),

            'version' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.sdk_version'),

            'credentials' => [
                'key' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.key'),
                'secret' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.secret'),
            ],
        ];

        if (blank(array_filter($config['credentials']))) {
            return null;
        }

        return new CloudFrontClient([
            'region' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.region'),

            'version' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.sdk_version'),

            'credentials' => [
                'key' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.key'),
                'secret' => Helpers::configString('edge-flush.services.'.$this->serviceId.'.secret'),
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

        if (!is_array($list)) {
            return false;
        }

        if (isset($list['Items']) && is_array($list['Items'])) {
            return (new Collection($list['Items']))->where('Status', 'InProgress')->count() > 0;
        }

        return false;
    }

    public function createInvalidationRequest(Invalidation|array $invalidation = null): Invalidation
    {
        $invalidation = $this->createInvalidation($invalidation);

        $paths = $invalidation->paths()->toArray();

        Helpers::debug(
            '[CLOUD FRONT]: Invalidating ' .
            count($paths) .
            ' path(s): (' .
            (new Collection($paths))->take(20)->implode(', ') .
            ')...',
        );

        if (!$this->isProperlyConfigured()) {
            Helpers::debug('[CLOUD FRONT]: Service is disabled.');

            return $invalidation;
        }

        try {
            $response = $this->client->createInvalidation([
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
                '[EDGE-FLUSH] [CLOUD FRONT] Invalidation request failed: ' .
                $e->getMessage() .
                ' - PATHS: ' .
                json_encode($paths),
            );

            return $invalidation;
        }

        return $invalidation->absorb($response);
    }

    public function isProperlyConfigured(): bool
    {
        return filled($this->client);
    }
}
