<?php

declare(strict_types=1);

namespace Ironflow\Mail;

use Ironflow\Application;
use Ironflow\Template\Engine as TemplateEngine;
use Symfony\Component\Mime\Email;

/**
 * Base class for reusable, testable email messages.
 *
 * Subclass and implement build():
 *
 *   class WelcomeMail extends Mailable
 *   {
 *       public function __construct(private User $user) {}
 *
 *       public function build(array $config = []): Email
 *       {
 *           return $this->makeEmail()
 *               ->to($this->user->email)
 *               ->subject('Welcome to IronFlow')
 *               ->html($this->renderView('@common/emails/welcome', ['user' => $this->user]));
 *       }
 *   }
 */
abstract class Mailable
{
    abstract public function build(array $config = []): Email;

    protected function makeEmail(): Email
    {
        return new Email();
    }

    protected function renderView(string $template, array $data = []): string
    {
        $engine = Application::getInstance()->getContainer()->make(TemplateEngine::class);
        return $engine->render($template, $data);
    }
}
