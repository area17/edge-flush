<?php declare(strict_types=1);

namespace A17\EdgeFlush\Jobs;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Bus\Queueable;
use A17\EdgeFlush\Services\Entity;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InvalidateModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Entity|null $entity = null;

    /**
     * Create a new job instance.
     */
    public function __construct(Entity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        EdgeFlush::tags()->dispatchInvalidationsForModel($this->entity);
    }
}
