<?php declare(strict_types=1);

namespace A17\EdgeFlush\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $model
 * @property string $tag
 * @property int $url_id
 * @property Url $url
 */
class Tag extends Model
{
    protected $table = 'edge_flush_tags';

    protected $fillable = ['index', 'model', 'tag', 'url_id'];

    protected $with = ['url'];

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
