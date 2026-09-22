<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Model;
use Ironflow\Database\Relations\HasMany;

class RelationAuthorModel extends Model
{
    protected string $table = 'rel_authors';
    protected array $fillable = ['country_id', 'name'];

    public function posts(): HasMany
    {
        return $this->hasMany(RelationPostModel::class, 'author_id');
    }
}
