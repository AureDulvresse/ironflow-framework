<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Concerns\SoftDeletes;
use Ironflow\Database\Model;

class SoftDeleteArticleModel extends Model
{
    use SoftDeletes;

    protected string $table    = 'trashable_articles';
    protected array  $fillable = ['title'];
}
