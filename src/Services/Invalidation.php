<?php

namespace A17\EdgeFlush\Services;

use Carbon\Carbon;
use Aws\Result as AwsResult;
use A17\EdgeFlush\Support\Helpers;

class Invalidation
{
    protected string|null $id = null;

    protected string|null $status = null;

    protected bool $success = false;

    protected Carbon|null $createdAt = null;

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

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setCreatedAt(Carbon|string $createdAt): self
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

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): Carbon|null
    {
        return $this->status;
    }

    public function absorbCloudFront(AwsResult $invalidation): self
    {
        $this->success = filled($invalidation);

        if (!$this->success()) {
            return $this;
        }

        $this->id = $invalidation['Invalidation']['Id'];

        $this->status = $invalidation['Invalidation']['Status'];

        $this->createdAt = Carbon::parse(
            (string) $invalidation['Invalidation']['CreateTime'],
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
}
