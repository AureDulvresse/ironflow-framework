<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Model;

class RaceModel extends Model
{
    protected string $table = 'race_models';
    protected array $fillable = ['email', 'name'];
}
