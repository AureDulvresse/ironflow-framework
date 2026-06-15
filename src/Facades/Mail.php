<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static \Ironflow\Mail\PendingMail to(string|array $recipients)
 * @method static void send(\Ironflow\Mail\Mailable|\Symfony\Component\Mime\Email $message)
 */
class Mail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Mail\Mailer::class;
    }
}
