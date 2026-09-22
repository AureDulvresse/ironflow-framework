<?php

declare(strict_types=1);

namespace Ironflow\Validation;

use Ironflow\Application;
use Ironflow\Database\Connection;

/**
 * Factory to create ValidatorInstance objects.
 * Injected via the container; exposes make() matching the Validator facade API.
 */
class ValidatorFactory
{
    public function make(array $data, array $rules, array $messages = []): ValidatorInstance
    {
        // No try/catch: a Connection resolution failure is a real misconfiguration.
        // Swallowing it here used to make every `unique:`/`exists:` rule pass
        // silently instead — a validation bypass, not just a style issue.
        $db = Application::getInstance()->getContainer()->make(Connection::class);

        return new ValidatorInstance($data, $rules, $messages, $db);
    }
}
