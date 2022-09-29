<?php

namespace A17\EdgeFlush\Services\CloudFront;

use Aws\AwsClient;
use Aws\Result as AwsResult;
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
use A17\EdgeFlush\Services\Invalidation;
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

    public function invalidate(Invalidation $invalidation): Invalidation
    {
        if (!$this->enabled()) {
            return $invalidation;
        }

        return $this->mustInvalidateAll($invalidation)
            ? $this->invalidateAll()
            : $this->invalidatePaths($invalidation);
    }

    public function invalidateAll(): Invalidation
    {
        if (!$this->enabled()) {
            return $this->unsuccessfulInvalidation();
        }

        return $this->createInvalidationRequest(
            Helpers::configArray(
                'edge-flush.services.cloud_front.invalidate_all_paths',
            ),
        );
    }

    protected function getDistributionId(): string|null
    {
        return Helpers::configString(
            'edge-flush.services.cloud_front.distribution_id',
        );
    }

    public function getClient(): CloudFrontClient|null
    {
        $config = [
            'region' => Helpers::configString(
                'edge-flush.services.cloud_front.region',
            ),

            'version' => Helpers::configString(
                'edge-flush.services.cloud_front.sdk_version',
            ),

            'credentials' => [
                'key' => Helpers::configString(
                    'edge-flush.services.cloud_front.key',
                ),
                'secret' => Helpers::configString(
                    'edge-flush.services.cloud_front.secret',
                ),
            ],
        ];

        if (blank(array_filter($config['credentials']))) {
            return null;
        }

        return new CloudFrontClient([
            'region' => Helpers::configString(
                'edge-flush.services.cloud_front.region',
            ),

            'version' => Helpers::configString(
                'edge-flush.services.cloud_front.sdk_version',
            ),

            'credentials' => [
                'key' => Helpers::configString(
                    'edge-flush.services.cloud_front.key',
                ),
                'secret' => Helpers::configString(
                    'edge-flush.services.cloud_front.secret',
                ),
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
            return collect($list['Items'])
                ->where('Status', 'InProgress')
                ->count() > 0;
        }

        return false;
    }

    public function createInvalidationRequest(
        Invalidation|array $invalidation = null
    ): Invalidation {
        $invalidation = $this->createInvalidation($invalidation);

        $paths = $invalidation->paths();

        Helpers::debug(
            '[CLOUD FRONT]: Invalidating ' .
                count($paths) .
                ' path(s): (' .
                collect($paths)
                    ->take(20)
                    ->implode(', ') .
                ')...',
        );

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
                'CDN: CloudFront invalidation request failed: ' .
                    $e->getMessage() .
                    ' - PATHS: ' .
                    json_encode($paths),
            );

            return $invalidation;
        }

        return $invalidation->absorb($response);
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

        $url = Helpers::getUrl($item);

        if ($url === null) {
            return null;
        }

        return Helpers::parseUrl($url)['path'] ?? '/*';
    }

    public function getInvalidationPathsForTags(
        Invalidation $invalidation
    ): Collection {
        if ($invalidation->paths()->isEmpty()) {
            $paths = collect($invalidation->tags())
                ->mapWithKeys(
                    fn($tag) => [$this->getInvalidationPath($tag) => $tag],
                )
                ->keys()
                ->unique()
                ->take($this->maxUrls());

            $invalidation->setPaths($paths);
        }

        return $invalidation->paths();
    }

    public function mustInvalidateAll(Invalidation $invalidation): bool
    {
        return $this->getInvalidationPathsForTags($invalidation)->count() >=
            $this->maxUrls();
    }

    public function invalidatePaths(Invalidation $invalidation): Invalidation
    {
        return $this->createInvalidationRequest($invalidation);
    }

    public function maxUrls(): int
    {
        return Helpers::configInt('edge-flush.services.cloud_front.max_urls') ??
            300;
    }

    public function enabled(): bool
    {
        return parent::enabled() &&
            Helpers::configBool(
                'edge-flush.services.cloud_front.enabled',
                true,
            ) &&
            filled($this->getClient());
    }

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        $response = $this->getInvalidation($invalidationId);

        if (blank($response)) {
            return false;
        }

        return Invalidation::factory($response)->isCompleted();
    }

    public function getInvalidation(string $invalidationId): AwsResult
    {
        return $this->client->getInvalidation([
            'DistributionId' => $this->getDistributionId(),
            'Id' => $invalidationId,
        ]);
    }
}
