<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Attributes\Column;
use Ironflow\Database\Attributes\Table;
use Ironflow\Database\Model;

#[Table('articles')]
#[Column('title')]
#[Column('body')]
#[Column('published', cast: 'bool')]
#[Column('internal_notes', fillable: false, hidden: true)]
class AttributeArticleModel extends Model
{
}
