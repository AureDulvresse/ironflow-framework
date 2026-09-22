<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Model;

class EncryptedFieldModel extends Model
{
    protected string $table = 'secrets';
    protected array $fillable = ['ssn'];
    protected array $casts = ['ssn' => 'encrypted'];
}
