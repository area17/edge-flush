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
use Illuminate\Database\Events\QueryExecuted;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Behaviours\ControlsInvalidations;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Tags
{
    use ControlsInvalidations, MakeTag, Database;

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
        if (!$this->wasNotProcessed($model)) {
            return;
        }

        if (!EdgeFlush::enabled() || blank($model->getAttributes()[$key] ?? null)) {
            return;
        }

        $tags = [
            $this->makeModelName($model, Constants::ANY_TAG, $allowedKeys),
            $this->makeModelName($model, $key, $allowedKeys),
        ];

        foreach ($tags as $tag) {
            if (blank($this->tags[$tag] ?? null)) {
                $this->tags[$tag] = $tag;
            }
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

    public function tagIsExcluded(string $tag): bool
    {
        /**
         * @param callable(string $pattern): boolean $pattern
         */
        return Helpers::collect(config('edge-flush.tags.excluded-model-classes'))->contains(
            fn(string $pattern) => EdgeFlush::match($pattern, $tag),
        );
    }

    public function tagIsNotExcluded(string $tag): bool
    {
        return !$this->tagIsExcluded($tag);
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
        // TODO: review this because there's no more obsolete on Tags

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

    public function dispatchInvalidationsForModel(Collection|string|Model $models): void
    {
        if (blank($models)) {
            return;
        }

        if (is_string($models)) {
            $this->dispatchInvalidationsForUpdatedModel(Helpers::collect([$models]));

            return;
        }

        if ($models instanceof Model) {
            $models = Helpers::collect([$models]);
        }

        $models = $this->onlyValidModels($models);

        $models = $this->notYetDispatched($models);

        if ($models->isEmpty()) {
            return;
        }

        /**
         * @var Model $model
         */
        $model = $models->first();

        $model->wasRecentlyCreated
            ? $this->dispatchInvalidationsForCreatedModel($models)
            : $this->dispatchInvalidationsForUpdatedModel($models);
    }

    public function dispatchInvalidationsForCreatedModel(Collection $models): void
    {
        /**
         * @var string $strategy
         */
        $strategy = config('edge-flush.crud-strategy.update.strategy', 'invalidate-all');

        if ($strategy === 'invalidate-all') {
            $this->invalidateAll(true);

            return;
        }

        throw new \Exception("Strategy '{$strategy}' Not implemented");
    }

    public function dispatchInvalidationsForUpdatedModel(Collection $models): void
    {
        /**
         * @var string $strategy
         */
        $strategy = config('edge-flush.crud-strategy.update.strategy', 'invalidate-dependents');

        if ($strategy === 'invalidate-all') {
            $this->invalidateAll(true);

            return;
        }

        if ($strategy !== 'invalidate-dependents') {
            throw new \Exception("Strategy '{$strategy}' Not implemented");
        }

        $modelNames = $this->getModelNamesFromModels($models);

        Helpers::debug('INVALIDATING tags for models: ' . $modelNames->join(', '));

        dispatch(new InvalidateTags((new Invalidation())->setModels($modelNames)));
    }

    protected function getModelNamesFromModels(Collection $models): Collection
    {
        $modelNames = Helpers::collect();

        /**
         * @var Model $model
         */
        foreach ($models as $model) {
            foreach ($model->getAttributes() as $key => $updated) {
                if ($updated !== $model->getOriginal($key) && $this->granularPropertyIsAllowed($key, $model)) {
                    $modelName = $this->makeModelName($model, $key);

                    if (filled($modelName)) {
                        $modelNames->push($modelName);
                    }
                }
            }
        }

        return $modelNames;
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
    protected function wasNotProcessed(Model $model): bool
    {
        $id = $model->getAttributes()[$model->getKeyName()] ?? null;

        if ($id === null) {
            return false; /// don't process models with no ID yet
        }

        $key = $model->getTable() . '-' . $id;

        if (filled($this->processedTags[$key] ?? null) && (bool) $this->processedTags[$key]) {
            return false;
        }

        $this->processedTags[$key] = true;

        return true;
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

            dump($statement);

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

    public function onlyValidModels(Collection $models): Collection
    {
        return $models->filter(function ($model) {
            if ($model instanceof Model) {
                $model = get_class($model);
            }

            if (!is_string($model)) {
                return false;
            }

            return $this->tagIsNotExcluded($model);
        });
    }

    public function notYetDispatched(Collection $models): Collection
    {
        $tags = $models->mapWithKeys(function ($model) {
            $tag = $this->makeModelName($model);

            return [$tag => $tag];
        });

        $missing = $tags->diff($this->invalidationDispatched);

        $this->invalidationDispatched = $this->invalidationDispatched->merge($missing);

        return $models->filter(fn($model) => $missing->contains($this->makeModelName($model)));
    }

    public function granularPropertyIsAllowed(string $name, Model $model): bool
    {
        $ignored = Helpers::collect(Helpers::configArray('edge-flush.invalidations.properties.ignored'));

        return !$ignored->contains($name) && !$ignored->contains(get_class($model) . "@$name");
    }

    public function cannotStoreCacheTags(string $url): bool
    {
        return !EdgeFlush::enabled() || !EdgeFlush::storeTagsServiceIsEnabled() || !$this->domainAllowed($url);
    }
}
