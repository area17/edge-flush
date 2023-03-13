<?php declare(strict_types=1);

namespace A17\EdgeFlush\Jobs;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InvalidateModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Model|null $model = null;

    public string|null $type = null;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model, string|null $type = null)
    {
        $this->model = $model->withoutRelations();

        $this->model = EdgeFlush::getInternalModel($this->model);

        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        EdgeFlush::tags()->dispatchInvalidationsForModel($this->model, $this->type);
    }
}
