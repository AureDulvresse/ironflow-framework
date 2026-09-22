<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Concerns\SoftDeletes;
use Ironflow\Database\Model;

class RelationTagModel extends Model
{
    use SoftDeletes;

    protected string $table = 'rel_tags';
    protected array $fillable = ['name'];
}
