<?php declare(strict_types=1);

namespace A17\EdgeFlush\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $url
 * @property string $url_hash
 * @property int $hits
 * @property Carbon $was_purged_at
 * @property string $invalidation_id
 * @property bool $canBeSaved
 */
class Url extends Model
{
    protected $table = 'edge_flush_urls';

    protected $fillable = ['url', 'url_hash', 'hits', 'was_purged_at', 'invalidation_id', 'is_valid'];

    protected $casts = ['was_purged_at' => 'datetime'];

    public function incrementHits(): void
    {
        $this->hits++;

        $this->save();
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function getEdgeCacheTag(): string
    {
        return $this->url_hash;
    }
}
