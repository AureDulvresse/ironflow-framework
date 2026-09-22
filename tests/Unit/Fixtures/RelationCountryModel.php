<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Model;
use Ironflow\Database\Relations\HasManyThrough;

class RelationCountryModel extends Model
{
    protected string $table = 'rel_countries';
    protected array $fillable = ['name'];

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(RelationPostModel::class, RelationAuthorModel::class, 'country_id', 'author_id');
    }
}
