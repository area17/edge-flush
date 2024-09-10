<?php

namespace A17\EdgeFlush\Models;

use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use App\Twill\Capsules\People\Models\Person;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    use SerializesModels;

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        if (count($this->model->getAttributes()) > 0)
        {
            return;
        }

        $eventData = json_encode(['model' => get_class($this->model), 'attributes' => $this->compileAttributesFromQuery($values), 'updates' => $values]);

        Event::dispatch('eloquent.builder.updating: ' . get_class($this->model), $eventData);

        $value = $this->toBase()->update($this->addUpdatedAtColumn($values));

        Event::dispatch('eloquent.builder.updated: ' . get_class($this->model), $eventData);
    }

    public function compileAttributesFromQuery(array $values): array
    {
        $data = [];

        foreach($this->query->wheres as $where)
        {
            if ($where['type'] == 'Basic')
            {
                if ($where['column'] !== 'id' || $where['value'] != 0) {
                    $data[$where['column']] = $where['value'];
                }
            }
        }

        return array_merge($data, $values);
    }
}
