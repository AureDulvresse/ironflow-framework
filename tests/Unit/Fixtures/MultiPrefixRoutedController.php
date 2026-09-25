<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Routing\Attributes\Route;

#[Route('/posts')]
#[Route('/articles')]
class MultiPrefixRoutedController
{
    #[Route('/{id}', name: 'posts.show')]
    public function show(): void
    {
    }
}
