<?php

declare(strict_types=1);

namespace Ironflow\Http;

use Ironflow\Application;
use Ironflow\Auth\Gate;
use Ironflow\Auth\PolicyGate;
use Ironflow\Exceptions\HttpException;

/**
 * Base controller for web (and any) controllers.
 *
 * Provides small, convenient helpers shared across controllers:
 *
 *   return $this->view('@blog/posts/index', compact('posts'));
 *   return $this->json(['ok' => true]);
 *   return $this->redirect('/login');
 *   return $this->back();
 *
 *   $this->authorize('update-post', $post);   // 403 if denied
 *   if ($this->can('delete-post', $post)) { ... }
 *
 *   $data = $this->validate($request, ['title' => 'required']);
 *
 * Controllers are plain classes — extending this is optional but removes
 * boilerplate. For JSON-first APIs, extend {@see ApiController} instead, which
 * builds on this base.
 */
abstract class Controller
{
    /**
     * Middleware this controller declares for itself. Routes can read this to
     * apply controller-wide middleware. Override in subclasses, e.g.:
     *
     *   protected array $middleware = ['auth', 'throttle:60,1'];
     *
     * @var array<string>
     */
    protected array $middleware = [];

    /** @return array<string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    // ── Response helpers ──────────────────────────────────────────────

    /** Render a Twig view to an HTML response. */
    protected function view(string $template, array $data = []): Response
    {
        return Response::view($template, $data);
    }

    /** Build a JSON response. */
    protected function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /** Redirect to a URL. */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /** Redirect to a named route. */
    protected function redirectToRoute(string $name, array $params = [], int $status = 302): RedirectResponse
    {
        return (new RedirectResponse('', $status))->route($name, $params);
    }

    /** Redirect back to the previous page (Referer), falling back to '/'. */
    protected function back(int $status = 302): RedirectResponse
    {
        $referer = '/';
        try {
            $request = Application::getInstance()->getContainer()->make(Request::class);
            $referer = $request->headers->get('referer') ?: '/';
        } catch (\Throwable) {
        }
        return new RedirectResponse($referer, $status);
    }

    // ── Validation ────────────────────────────────────────────────────

    /**
     * Validate request input, returning the validated subset.
     * Throws a ValidationException (→ 422 / redirect-back) on failure.
     *
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        return $request->validate($rules, $messages);
    }

    // ── Authorization ─────────────────────────────────────────────────

    /** Abort with 403 if the current user cannot perform the given ability. */
    protected function authorize(string $ability, mixed $arguments = []): void
    {
        try {
            $this->gate()->authorize($ability, $arguments);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new HttpException(403, "Cette action n'est pas autorisée.");
        }
    }

    protected function can(string $ability, mixed $arguments = []): bool
    {
        try {
            return $this->gate()->allows($ability, $arguments);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function cannot(string $ability, mixed $arguments = []): bool
    {
        return !$this->can($ability, $arguments);
    }

    // ── Policy authorization (AdonisJS-v6 bouncer style) ──────────────

    /**
     * Get an authorizer scoped to an explicit policy class, then check
     * abilities fluently:
     *
     *   $this->bouncer(PostPolicy::class)->authorize('edit', $post);
     *   if ($this->bouncer(PostPolicy::class)->allows('delete', $post)) { ... }
     *
     * Unlike authorize()/can(), which resolve the policy from the model by
     * convention, this targets the policy you name explicitly.
     */
    protected function bouncer(string $policyClass): PolicyGate
    {
        return $this->gate()->with($policyClass);
    }

    /**
     * Assert the current user passes an ability on an explicit policy,
     * aborting with 403 on denial. Shorthand for bouncer()->authorize().
     *
     *   $this->authorizePolicy(PostPolicy::class, 'update', $post);
     */
    protected function authorizePolicy(string $policyClass, string $ability, mixed ...$arguments): void
    {
        $this->bouncer($policyClass)->authorize($ability, ...$arguments);
    }

    private function gate(): Gate
    {
        return Application::getInstance()->getContainer()->make(Gate::class);
    }
}
