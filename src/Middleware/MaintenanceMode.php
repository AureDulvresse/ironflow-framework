<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Application;
use Ironflow\Exceptions\HttpException;
use Ironflow\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function __construct(private readonly Application $app)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $flag = $this->app->path('storage', 'maintenance.flag');

        if (!is_file($flag)) {
            return $next($request);
        }

        // Allow bypass via a secret cookie
        $secret = trim((string) file_get_contents($flag));
        if ($secret && $request->cookies->get('maintenance_bypass') === $secret) {
            return $next($request);
        }

        throw new HttpException(503, 'Service Unavailable — maintenance mode.');
    }
}
