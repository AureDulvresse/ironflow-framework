<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Concerns\SoftDeletes;
use Ironflow\Database\Model;
use Ironflow\Database\Relations\BelongsTo;
use Ironflow\Database\Relations\BelongsToMany;

class RelationPostModel extends Model
{
    use SoftDeletes;

    protected string $table = 'rel_posts';
    protected array $fillable = ['author_id', 'title'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(RelationAuthorModel::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RelationTagModel::class, 'rel_post_tag', 'post_id', 'tag_id')
            ->withPivot('sort_order');
    }
}
