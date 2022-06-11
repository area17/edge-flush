<?php

namespace A17\EdgeFlush\Services;

use Carbon\Carbon;

class Invalidation
{
    protected string|null $id;

    protected string|null $status;

    protected bool $success = false;

    protected Carbon|null $createdAt;

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

    public function id(): string
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

    public function absorbCloudFrontInvalidation(
        \Aws\Result $invalidation
    ): self {
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
}
