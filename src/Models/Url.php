<?php

namespace A17\EdgeFlush\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $url
 * @property string $url_hash
 * @property int $hits
 * @property bool $was_purged_at
 * @property string $invalidation_id
 */
class Url extends Model
{
    protected $table = 'edge_flush_urls';

    protected $fillable = [
        'url',
        'url_hash',
        'hits',
        'was_purged_at',
        'invalidation_id',
        'is_valid',
    ];

    public function incrementHits(): void
    {
        $this->hits++;

        $this->save();
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }
}
