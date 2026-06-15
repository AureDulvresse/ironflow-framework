<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Closure;
use Ironflow\Container;
use Ironflow\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pipeline — sends a Request through an ordered stack of middlewares
 * then calls the final destination handler.
 *
 * Two middleware styles are supported:
 *
 *  Onion-style (PSR-15 inspired):
 *    handle(Request $req, callable $next, ...$params): Response
 *
 *  Hook-style (Django-inspired):
 *    processRequest(Request $req, ...$params): ?Response   — early-return skips the chain
 *    processResponse(Request $req, Response $res, ...$params): Response
 *
 * Pipe syntax:
 *   'App\Middleware\Foo'          — FQCN, no parameters
 *   'App\Middleware\Foo:a,b'      — FQCN with comma-separated parameters
 *   $objectInstance               — pre-instantiated middleware object
 */
class Pipeline
{
    private Request $passable;
    private array   $pipes = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function send(Request $request): static
    {
        $this->passable = $request;
        return $this;
    }

    public function through(array $middlewares): static
    {
        $this->pipes = $middlewares;
        return $this;
    }

    public function then(callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            fn(Request $req): Response => $destination($req)
        );

        return $pipeline($this->passable);
    }

    private function carry(): Closure
    {
        return function (Closure $next, mixed $pipe): Closure {
            return function (Request $request) use ($next, $pipe): Response {
                [$middleware, $params] = $this->resolve($pipe);

                $hasProcessRequest  = method_exists($middleware, 'processRequest');
                $hasProcessResponse = method_exists($middleware, 'processResponse');

                // Hook-style: either processRequest and/or processResponse.
                if ($hasProcessRequest || $hasProcessResponse) {
                    if ($hasProcessRequest) {
                        $early = $middleware->processRequest($request, ...$params);
                        if ($early instanceof Response) {
                            return $early;
                        }
                    }

                    $response = $next($request);

                    if ($hasProcessResponse) {
                        $response = $middleware->processResponse($request, $response, ...$params);
                    }

                    return $response;
                }

                // Onion-style: handle($request, $next, ...$params)
                if (method_exists($middleware, 'handle')) {
                    return $middleware->handle($request, $next, ...$params);
                }

                throw new \RuntimeException(sprintf(
                    'Middleware [%s] must define handle($request, $next) or '
                    . 'processRequest()/processResponse().',
                    get_debug_type($middleware)
                ));
            };
        };
    }

    /**
     * Resolve a pipe entry to [object, params[]].
     *
     * Accepted forms:
     *   - object instance  → used directly
     *   - 'FQCN'           → resolved via container, no params
     *   - 'FQCN:a,b'       → resolved via container, params = ['a', 'b']
     */
    private function resolve(mixed $pipe): array
    {
        if (is_object($pipe)) {
            return [$pipe, []];
        }

        if (!is_string($pipe)) {
            throw new \InvalidArgumentException(
                'Middleware must be a class name string or an object instance, got ' . gettype($pipe)
            );
        }

        $params = [];
        if (str_contains($pipe, ':')) {
            [$pipe, $paramStr] = explode(':', $pipe, 2);
            $params = array_map('trim', explode(',', $paramStr));
        }

        return [$this->container->make($pipe), $params];
    }
}
