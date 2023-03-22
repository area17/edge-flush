<?php declare(strict_types=1);

namespace A17\EdgeFlush\Models;

use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $model
 * @property string $tag
 * @property int $url_id
 * @property Url $url
 */
class Tag extends Model
{
    use CachedOnCDN;

    protected $table = 'edge_flush_tags';

    protected $fillable = ['index_hash', 'model', 'tag', 'url_id'];

    protected $with = ['url'];

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
