<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Http\ContentSecurityPolicy;
use Ironflow\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds common security headers to every response.
 *
 * Configurable via config/middleware.php → 'security_headers' (associative
 * array of header => value; set value to false to omit a default).
 *
 * Content-Security-Policy is built separately via the 'csp' config key:
 *   'csp' => [
 *       'enabled' => true,
 *       'preset'  => 'strict',          // 'strict' | 'relaxed' | null
 *       'directives' => [               // merged onto the preset
 *           'script-src' => ['https://cdn.example.com'],
 *       ],
 *       'report_only' => false,
 *   ]
 *
 * HSTS is added automatically on HTTPS requests (configurable).
 */
class SecurityHeaders
{
    private const DEFAULTS = [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
        'Cross-Origin-Opener-Policy'   => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $config = $this->config();

        // Static headers
        $headers = array_merge(self::DEFAULTS, $config['security_headers'] ?? []);
        foreach ($headers as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $response->headers->set($name, (string) $value);
        }

        // HSTS — only meaningful over HTTPS
        if ($request->isSecure()) {
            $hsts = $config['hsts'] ?? ['max_age' => 31536000, 'include_subdomains' => true, 'preload' => false];
            if (($hsts['max_age'] ?? 0) > 0) {
                $value = 'max-age=' . (int) $hsts['max_age'];
                if (!empty($hsts['include_subdomains'])) {
                    $value .= '; includeSubDomains';
                }
                if (!empty($hsts['preload'])) {
                    $value .= '; preload';
                }
                $response->headers->set('Strict-Transport-Security', $value);
            }
        }

        // Content-Security-Policy
        $this->applyCsp($response, $config['csp'] ?? []);

        return $response;
    }

    private function applyCsp(Response $response, array $csp): void
    {
        if (empty($csp) || ($csp['enabled'] ?? false) === false) {
            return;
        }

        $policy = match ($csp['preset'] ?? 'strict') {
            'relaxed' => ContentSecurityPolicy::relaxed(),
            'strict'  => ContentSecurityPolicy::strict(),
            default   => new ContentSecurityPolicy(),
        };

        foreach (($csp['directives'] ?? []) as $directive => $sources) {
            $policy->allow($directive, (array) $sources);
        }

        $header = !empty($csp['report_only'])
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $policy->compile());
    }

    private function config(): array
    {
        try {
            return (array) \Ironflow\Application::getInstance()
                ->getContainer()
                ->make(\Ironflow\Config\Repository::class)
                ->get('middleware', []);
        } catch (\Throwable) {
            return [];
        }
    }
}
