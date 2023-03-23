<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use A17\EdgeFlush\EdgeFlush;
use Illuminate\Http\Request;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Jobs\StoreTags;
use SebastianBergmann\Timer\Timer;
use Illuminate\Support\Facades\DB;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Constants;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Query\Builder;
use A17\EdgeFlush\Jobs\InvalidateTags;
use A17\EdgeFlush\Behaviours\Database;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Behaviours\CastObject;
use Illuminate\Database\Events\QueryExecuted;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Behaviours\ControlsInvalidations;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Tags
{
    use ControlsInvalidations, MakeTag, Database, CastObject;

    protected Collection $tags;

    protected Collection $invalidationDispatched;

    public Collection $processedTags;

    public function __construct()
    {
        $this->tags = Helpers::collect();

        $this->processedTags = Helpers::collect();

        $this->invalidationDispatched = Helpers::collect();
    }

    public function addTag(Model $model, string $key = null, array $allowedKeys = []): void
    {
        if (!EdgeFlush::enabled()) {
            return;
        }

        if ($this->attributeMustBeIgnored($model, $key) || blank($model->getAttributes()[$key] ?? null)) {
            return;
        }

        $tag = $this->makeModelName($model, $key, $allowedKeys);

        if (blank($tag) || $this->alreadyProcessed($tag)) {
            return;
        }

        $tags = [$tag];

        // $tags[] = $this->makeModelName($model, Constants::ANY_TAG, $allowedKeys), // TODO: do we need the ANY_TAG?

        foreach ($this->getAlwaysAddAttributes($model) as $attrribute) {
            if ($model->hasAttribute($attrribute)) {
                $tags[] = $this->makeModelName($model, $attrribute, $allowedKeys);
            }
        }

        foreach ($tags as $tag) {
            if (blank($this->tags[$tag] ?? null)) {
                $this->tags[$tag] = $tag;
            }
        }

        if ($key === 'intro_text') {
            info('intro_text 5 - attributeMustBeIgnored');
        }
    }

    protected function getAllTagsForModel(string|null $modelString): Collection|null
    {
        if (filled($modelString)) {
            return Tag::where('model', $modelString)->get();
        }

        return null;
    }

    public function getTags(): Collection
    {
        return Helpers::collect($this->tags)
            ->reject(function (string $tag) {
                return $this->tagIsExcluded($tag);
            })
            ->values();
    }

    public function getTagsHash(Response $response, Request $request): string
    {
        $tag = $this->makeEdgeTag($models = $this->getTags());

        if (EdgeFlush::cacheControl()->isCachable($response) && EdgeFlush::storeTagsServiceIsEnabled()) {
            StoreTags::dispatch(
                $models,
                [
                    'cdn' => $tag,
                ],
                $this->getCurrentUrl($request),
            );
        }

        return $tag;
    }

    public function makeEdgeTag(Collection|null $models = null): string
    {
        $models ??= $this->getTags();

        $format = Helpers::toString(config('edge-flush.tags.format', 'app-%environment%-%sha1%'));

        return str_replace(
            ['%environment%', '%sha1%'],
            [
                app()->environment(),
                sha1(
                    Helpers::collect($models)
                        ->sort()
                        ->join(', '),
                ),
            ],
            $format,
        );
    }

    public function storeCacheTags(Collection $models, array $tags, string $url): void
    {
        if ($this->cannotStoreCacheTags($url)) {
            return;
        }

        Helpers::debug(
            'STORE-TAGS: ' .
                json_encode([
                    'models' => $models,
                    'tags' => $tags,
                    'url' => $url,
                ]),
        );

        $indexes = Helpers::collect();

        DB::transaction(function () use ($models, $tags, $url, &$indexes) {
            $url = $this->createUrl($url);

            $now = (string) now();

            $indexes = Helpers::collect($models)
                ->filter()
                ->map(function (mixed $model) use ($tags, $url, $now) {
                    $model = Helpers::toString($model);

                    $index = $this->makeTagIndex($url, $tags, $model);

                    $this->dbStatement($this->getStoreCacheTagsInsertSql($index, $url, $tags['cdn'], $model, $now));

                    return $index;
                });
        }, 5);

        if ($indexes->isNotEmpty()) {
            $indexes = $indexes->map(fn(mixed $item) => "'" . Helpers::toString($item) . "'")->join(',');

            $this->dbStatement($this->getStoreCacheTagsUpdateSql($indexes));
        }
    }

    protected function getStoreCacheTagsInsertSql(
        string $index,
        Url $url,
        string $cdn,
        string $model,
        string $now
    ): string {
        return "
            insert into edge_flush_tags (index_hash, url_id, tag, model, created_at, updated_at)
            select '{$index}', {$url->id}, '{$cdn}', '{$model}', '{$now}', '{$now}'
            where not exists (
                select 1
                from edge_flush_tags
                where index_hash = '{$index}'
            );
        ";
    }

    protected function getStoreCacheTagsUpdateSql(string $indexes): string
    {
        return "
            update edge_flush_urls
            set obsolete = false,
                was_purged_at = null,
                invalidation_id = null
            where is_valid = true
              and was_purged_at is not null
              and id in (
                select url_id
                from edge_flush_tags
                where index_hash in ({$indexes})
                  and is_valid = true
              );
        ";
    }

    public function dispatchInvalidationsForModel(Entity $entity): void
    {
        if (!$entity->isValid || $this->alreadyDispatched($entity)) {
            return;
        }

        Helpers::debug('DISPATCHING for model: ' . $entity->modelName);

        $strategy = $this->dispatchInvalidationsForCrud($entity);
    }

    public function dispatchInvalidationsForCrud(Entity $entity): void
    {
        $strategy = $this->getCrudStrategy($entity);

        if ($strategy === 'invalidate-none') {
            Helpers::debug('NO INVALIDATION needed for model ' . $entity->modelClass);

            return;
        }

        if ($strategy === 'invalidate-all') {
            Helpers::debug('INVALIDATING ALL tags');

            $this->invalidateAll(true);

            return;
        }

        if ($strategy === 'invalidate-dependents') {
            Helpers::debug('INVALIDATING tags for model ' . $entity->modelName);

            $invalidation = new Invalidation();

            $invalidation->setModels($entity->getDirtyModelNames());

            $this->invalidateTags($invalidation);

            return;
        }

        throw new \Exception("Strategy '{$strategy}' Not implemented");
    }

    public function getCrudStrategy(Entity $entity): string
    {
        $strategy = config("edge-flush.invalidations.crud-strategy.{$entity->event}");

        $defaultStrategy = $strategy['default'] ?? 'invalidate-dependents';

        if (blank($strategy)) {
            return $defaultStrategy;
        }

        foreach ($strategy['when-models'] ?? [] as $modelStrategy) {
            // Model is not in the list of models
            if (!in_array($entity->modelClass, $modelStrategy['models'])) {
                continue;
            }

            // There's no on-change condition
            if (blank($modelStrategy['on-change'] ?? null)) {
                return $modelStrategy['strategy'];
            }

            // Let's check if the attribute has changed to the expected value
            foreach ($modelStrategy['on-change'] as $key => $value) {
                // Did it change?
                // Is the expected value the same as the current value?
                // If key == value, then we're checking if the attribute was just changed
                if ($entity->isDirty($key) && ($key === $value || $entity->attributeEquals($key, $value))) {
                    return $modelStrategy['strategy'];
                }
            }
        }

        // No strategy found, let's use the default
        return $defaultStrategy;
    }

    public function invalidateTags(Invalidation $invalidation): void
    {
        if (!EdgeFlush::invalidationServiceIsEnabled()) {
            return;
        }

        if ($invalidation->isEmpty()) {
            $this->invalidateObsoleteTags();

            return;
        }

        config('edge-flush.invalidations.type') === 'batch'
            ? $this->markTagsAsObsolete($invalidation)
            : $this->dispatchInvalidations($invalidation);
    }

    protected function invalidateObsoleteTags(): void
    {
        if (!$this->enabled()) {
            return;
        }

        /**
         * Filter purged urls from obsolete tags.
         * Making sure we invalidate the most busy pages first.
         */
        $rows = Helpers::collect(
            DB::select(
                "
            select distinct edge_flush_urls.id, edge_flush_urls.hits, edge_flush_urls.url
            from edge_flush_urls
            where edge_flush_urls.was_purged_at is null
              and edge_flush_urls.obsolete = true
              and edge_flush_urls.is_valid = true
            order by edge_flush_urls.hits desc
            ",
            ),
        )->map(fn($row) => new Url((array) $row));

        $invalidation = (new Invalidation())->setUrls($rows);

        /**
         * Let's first calculate the number of URLs we are invalidating.
         * If it's above max, just flush the whole website.
         */
        if ($rows->count() >= EdgeFlush::cdn()->maxUrls()) {
            $this->invalidateEntireCache($invalidation);

            return;
        }

        /**
         * Let's dispatch invalidations only for what's configured.
         */
        $this->dispatchInvalidations($invalidation);
    }

    protected function markTagsAsObsolete(Invalidation $invalidation): void
    {
        $type = $invalidation->type();

        $list = $invalidation->queryItemsList();

        if ($list === "''" || is_null($type) || blank($type)) {
            return;
        }

        Helpers::debug($this->markTagsAsObsoleteSql($type, $list));

        $this->dbStatement($this->markTagsAsObsoleteSql($type, $list));
    }

    protected function markTagsAsObsoleteSql(string $type, string $list): string
    {
        if ($this->isMySQL()) {
            return "
                select id
                from edge_flush_urls
                where is_valid = true
                  and obsolete = false
                  and was_purged_at is null
                  and id in (
                        select url_id
                        from edge_flush_tags
                        where is_valid = true
                        and {$type} in ({$list})
                    )
                order by id
                for update;

                update edge_flush_urls efu
                set obsolete = true
                 where is_valid = true
                   and obsolete = false
                   and was_purged_at is null
                   and id in (
                         select url_id
                         from edge_flush_tags
                         where is_valid = true
                         and {$type} in ({$list})
                     );
            ";
        }

        return "
            update edge_flush_urls efu
            set obsolete = true
                from (
                         select id
                         from edge_flush_urls
                         where is_valid = true
                           and obsolete = false
                           and was_purged_at is null
                           and id in (
                                 select url_id
                                 from edge_flush_tags
                                 where is_valid = true
                                 and {$type} in ({$list})
                             )
                         order by id
                             for update
                     ) urls
                        where efu.id = urls.id
        ";
    }

    protected function dispatchInvalidations(Invalidation $invalidation): void
    {
        if ($invalidation->isEmpty() || !$this->enabled()) {
            return;
        }

        $invalidation = EdgeFlush::cdn()->invalidate($invalidation);

        if ($invalidation->success()) {
            // TODO: what happens here on Akamai?
            $this->markUrlsAsPurged($invalidation);
        }
    }

    protected function invalidateEntireCache(Invalidation $invalidation): void
    {
        if (!$this->enabled()) {
            return;
        }

        Helpers::debug('INVALIDATING: entire cache...');

        $invalidation->setMustInvalidateAll(true);

        EdgeFlush::cdn()->invalidate(
            $invalidation->setPaths(Helpers::collect(config('edge-flush.invalidations.batch.roots'))),
        );

        $this->markUrlsAsPurged($invalidation);
    }

    /*
     * Optimized for speed, 2000 calls to EdgeFlush::tags()->addTag($model) are now only 8ms
     */
    protected function alreadyProcessed(string $tag): bool
    {
        if (($this->processedTags[$tag] ?? null) === true) {
            return true;
        }

        $this->processedTags[$tag] = true;

        return false;
    }

    public function invalidateAll(bool $force = false): Invalidation
    {
        if (!$this->enabled() && !$force) {
            return $this->unsuccessfulInvalidation();
        }

        $count = 0;

        do {
            Helpers::debug('Invalidating all tags... -> ' . $count);

            if ($count++ > 0) {
                sleep(2);
            }

            $success = EdgeFlush::cdn()
                ->invalidateAll()
                ->success();
        } while ($count < 3 && !$success);

        if (!$success) {
            return $this->unsuccessfulInvalidation();
        }

        $this->deleteAllTags();

        return $this->successfulInvalidation();
    }

    public function getCurrentUrl(Request $request): string
    {
        /**
         * @var string|array|null $result
         */
        $result = $request->header('X-Edge-Flush-Warmed-Url') ?? url()->full();

        if (is_array($result)) {
            $result = $result[0] ?? '';
        }

        return $result;
    }

    protected function deleteAllTags(): void
    {
        $this->dbStatement($this->purgeAllUrlsSql());
    }

    protected function purgeAllUrlsSql(): string
    {
        $now = (string) now();

        if ($this->isMySQL()) {
            return "
                select id
                from edge_flush_urls
                where obsolete = false
                order by id
                for update;

                update edge_flush_urls efu
                set obsolete = true,
                    set was_purged_at = '$now'
                    where obsolete = false;
            ";
        }

        return "
            update edge_flush_urls efu
            set obsolete = true,
                set was_purged_at = '$now'
            from (
                    select id
                    from edge_flush_urls
                    where obsolete = false
                    order by id
                    for update
                ) urls
            where efu.id = urls.id
        ";
    }

    public function domainAllowed(string|null $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $allowed = Helpers::collect(config('edge-flush.domains.allowed'))->filter();

        $blocked = Helpers::collect(config('edge-flush.domains.blocked'))->filter();

        if ($allowed->isEmpty() && $blocked->isEmpty()) {
            return true;
        }

        $domain = Helpers::parseUrl($url)['host'];

        return $allowed->contains($domain) && !$blocked->contains($domain);
    }

    public function getMaxInvalidations(): int
    {
        return Helpers::toInt(min(EdgeFlush::cdn()->maxUrls(), config('edge-flush.invalidations.batch.size')));
    }

    public function dbStatement(string $sql): bool
    {
        $statements = explode(';', $sql);

        foreach ($statements as $statement) {
            if (blank($statement)) {
                continue;
            }

            $result = DB::statement($statement);

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    public function enabled(): bool
    {
        return EdgeFlush::invalidationServiceIsEnabled() && EdgeFlush::cdn()->enabled();
    }

    /**
     * @param string $url
     * @return Url
     */
    function createUrl(string $url): Url
    {
        $url = Helpers::sanitizeUrl($url);

        return Url::firstOrCreate(
            ['url_hash' => sha1($url)],
            [
                'url' => Str::limit($url, 255),
                'hits' => 1,
            ],
        );
    }

    public function makeTagIndex(Url|string $url, array $tags, string $model): string
    {
        if (is_string($url)) {
            $url = $this->createUrl($url);
        }

        $index = "{$url->id}-{$tags['cdn']}-{$model}";

        return sha1($index);
    }

    public function markUrlsAsPurged(Invalidation $invalidation): void
    {
        $list = $invalidation->queryItemsList('url');

        if ($list === "''") {
            return;
        }

        $time = (string) now();

        $invalidationId = $invalidation->id();

        if (is_null($invalidationId)) {
            return;
        }

        if ($invalidation->mustInvalidateAll()) {
            $sql = $this->invaliadateAllSql($time, $invalidationId);
        } elseif ($invalidation->type() === 'url') {
            $sql = $this->invalidateAllUrlsSql($list, $time, $invalidationId);
        } else {
            throw new \Exception('Invalidating ' . $invalidation->type() . ' is not supported yet.');
        }

        $this->dbStatement($sql);
    }

    protected function invaliadateAllSql(string $time, string $invalidationId): string
    {
        if ($this->isMySQL()) {
            return "
                select efu.id
                from edge_flush_urls efu
                where efu.is_valid = true
                  and was_purged_at is null
                   or obsolete = false
                order by efu.id
                for update;

                update edge_flush_urls efu
                set was_purged_at = '{$time}',
                    invalidation_id = '{$invalidationId}'
                where efu.is_valid = true
                  and was_purged_at is null
                   or obsolete = false;
            ";
        }

        return "
                update edge_flush_urls efu
                set was_purged_at = '{$time}',
                    invalidation_id = '{$invalidationId}'
                from (
                        select efu.id
                        from edge_flush_urls efu
                        where efu.is_valid = true
                          and was_purged_at is null
                           or obsolete = false
                        order by efu.id
                        for update
                    ) urls
                where efu.id = urls.id
            ";
    }

    protected function invalidateAllUrlsSql(string $list, string $time, string $invalidationId): string
    {
        if ($this->isMySQL()) {
            return "
                select efu.id
                from edge_flush_urls efu
                where efu.is_valid = true
                  and efu.url in ({$list})
                order by efu.id
                for update;

                update edge_flush_urls efu
                set was_purged_at = '{$time}',
                    invalidation_id = '{$invalidationId}'
                where efu.is_valid = true
                  and efu.url in ({$list});
            ";
        }

        return "
            update edge_flush_urls efu
            set was_purged_at = '{$time}',
                invalidation_id = '{$invalidationId}'
            from (
                    select efu.id
                    from edge_flush_urls efu
                    where efu.is_valid = true
                      and efu.url in ({$list})
                    order by efu.id
                    for update
                ) urls
            where efu.id = urls.id
        ";
    }

    public function markUrlsAsWarmed(Collection $urls): void
    {
        $list = $urls
            ->pluck('id')
            ->map(fn($item) => Helpers::toString($item))
            ->join(',');

        if ($list === "''") {
            return;
        }

        $this->dbStatement($this->markUrlsAsWarmedSql($list));
    }

    protected function markUrlsAsWarmedSql(string $list): string
    {
        if ($this->isMySQL()) {
            return "
                select efu.id
                from edge_flush_urls efu
                where efu.is_valid = true
                  and efu.id in ({$list})
                order by efu.id
                for update;

                update edge_flush_urls efu
                set obsolete = false,
                    was_purged_at = null,
                    invalidation_id = null
                where efu.is_valid = true
                  and efu.id in ({$list});
            ";
        }

        return "
            update edge_flush_urls efu
            set obsolete = false,
                was_purged_at = null,
                invalidation_id = null
            from (
                    select efu.id
                    from edge_flush_urls efu
                    where efu.is_valid = true
                      and efu.id in ({$list})
                    order by efu.id
                    for update
                ) urls
            where efu.id = urls.id
        ";
    }

    public function alreadyDispatched(Entity $entity): bool
    {
        $dispatched = $this->invalidationDispatched[$entity->modelName] ?? false;

        $this->invalidationDispatched[$entity->modelName] = true;

        return $dispatched;
    }

    public function cannotStoreCacheTags(string $url): bool
    {
        return !EdgeFlush::enabled() || !EdgeFlush::storeTagsServiceIsEnabled() || !$this->domainAllowed($url);
    }

    protected function attributeMustBeIgnored(Model $model, $attribute): bool
    {
        $attributes = config("edge-flush.invalidations.attributes.ignore", []);

        $ignore = ($attributes[get_class($model)] ?? []) + ($attributes['*'] ?? []);

        return in_array($attribute, $ignore);
    }

    protected function getAlwaysAddAttributes(Model $model): array
    {
        $attributes = config("edge-flush.invalidations.attributes.always-add", []);

        return ($attributes[get_class($model)] ?? []) + ($attributes['*'] ?? []);
    }
}
