<?php

namespace A17\EdgeFlush\Services;

use Carbon\Carbon;
use Aws\Result as AwsResult;
use A17\EdgeFlush\EdgeFlush;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Support\Constants;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Eloquent\Model;

class Invalidation
{
    use MakeTag;

    protected string|null $id = null;

    protected string|null $status = null;

    protected bool $success = false;

    protected string|null $type = null;

    protected Carbon|string|null $createdAt = null;

    protected Collection $tags;

    protected Collection $urls;

    protected Collection $paths;

    protected Collection $tagNames;

    protected Collection $models;

    protected Collection $modelNames;

    public function __construct()
    {
        $this->tags = collect();

        $this->urls = collect();

        $this->paths = collect();

        $this->tagNames = collect();

        $this->models = collect();

        $this->modelNames = collect();
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    public function setStatus(string|null $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setCreatedAt(Carbon|string|null $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function id(): string|null
    {
        return $this->id;
    }

    public function status(): string|null
    {
        return $this->status;
    }

    public function createdAt(): string|null
    {
        return $this->status;
    }

    public function absorbCloudFront(AwsResult $invalidation): self
    {
        $this->success = filled($invalidation);

        if (!$this->success()) {
            return $this;
        }

        $this->setId($invalidation['Invalidation']['Id'])
            ->setStatus($invalidation['Invalidation']['Status'])
            ->setCreatedAt(
                Carbon::parse(
                    (string) $invalidation['Invalidation']['CreateTime'],
                ),
            );

        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    public function absorb(AwsResult $object): self
    {
        if ($object instanceof AwsResult) {
            $this->absorbCloudFront($object);
        }

        return $this;
    }

    public static function factory(AwsResult $object): self
    {
        $self = new self();

        if (blank($object)) {
            return $self;
        }

        $self->absorb($object);

        Helpers::debug('INVALIDATION: ' . $self->toJson());

        return $self;
    }

    public function toJson(): string|false
    {
        return json_encode($this->toArray());
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'success' => $this->success,
            'created_at' => (string) $this->createdAt,
        ];
    }

    public function setTags(Collection $tags): self
    {
        $this->type = 'tag';

        $this->tags = $tags;

        return $this;
    }

    public function isEmpty(): bool
    {
        return blank($this->type()) ||
            ($this->tags()->isEmpty() && $this->models()->isEmpty());
    }

    public function setModels(Collection $models): self
    {
        $this->type = 'model';

        $this->models = $models;

        $this->modelNames = collect($models)->map(function (mixed $model) {
            if ($model instanceof Model) {
                return $this->makeModelName($model);
            }

            return $model;
        });

        return $this;
    }

    public function tags(): Collection
    {
        return $this->tags;
    }

    public function tagNames(): Collection
    {
        if ($this->isEmpty()) {
            return collect();
        }

        if (!$this->tagNames->isEmpty()) {
            return $this->tagNames;
        }

        return $this->tagNames = $this->tags()->map->tag;
    }

    public function modelNames(): Collection
    {
        if ($this->isEmpty()) {
            return collect();
        }

        if (!$this->modelNames->isEmpty()) {
            return $this->modelNames;
        }

        return $this->modelNames = $this->models()->map->model;
    }

    public function models(): Collection
    {
        return $this->models;
    }

    public function paths(): Collection
    {
        return $this->paths;
    }

    public function setPaths(Collection $paths): self
    {
        $this->type = 'path';

        $this->paths = $paths;

        return $this;
    }

    public function queryItemsList(string|null $type = null): string
    {
        return $this->makeQueryItemsList($this->items(), $type);
    }

    public function makeQueryItemsList(
        Collection $items,
        string|null $type = null
    ): string {
        return $this->items($type)
            ->map(fn($item) => "'$item'")
            ->join(',');
    }

    public function items(string|null $type = null): Collection
    {
        if ((blank($type) && $this->type === 'model') || $type === 'model') {
            return $this->modelNames();
        }

        if ((blank($type) && $this->type === 'tag') || $type === 'tag') {
            return $this->tagNames();
        }

        if ((blank($type) && $this->type === 'path') || $type === 'path') {
            return $this->paths();
        }

        return collect();
    }

    public function type(): string|null
    {
        return $this->type;
    }

    public function getInvalidationPaths(): Collection
    {
        /**
         * Get the actual list of paths that will be invalidated.
         * Never exceed the CDN max tags or urls that can be invalidated
         * at once.
         */
        return EdgeFlush::cdn()->getInvalidationPathsForTags($this);
    }
}
