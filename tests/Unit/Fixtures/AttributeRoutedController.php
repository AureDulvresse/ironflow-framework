<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Routing\Attributes\Route;

#[Route('/posts', middleware: 'web')]
class AttributeRoutedController
{
    #[Route('/', name: 'posts.index')]
    public function index(): void
    {
    }

    #[Route('/{id}', method: 'POST', name: 'posts.update', middleware: 'auth')]
    public function update(): void
    {
    }
}
