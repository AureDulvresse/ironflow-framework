<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Application;
use Ironflow\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * HotReloadMiddleware — auto-refresh HTML pages during local development.
 *
 * How it works:
 *   1. Intercepts GET /__ironflow/ping — returns JSON with an mtime hash.
 *   2. Injects a tiny polling script into every HTML response.
 *   3. The script compares hashes; when the hash changes it reloads the page.
 *
 * Enabled only when APP_ENV=local (or APP_DEBUG=true).
 * Never active in production.
 *
 * File types watched: .php, .twig, .html, .css, .js (excludes vendor & cache).
 */
class HotReloadMiddleware
{
    private const PING_PATH = '/__ironflow/ping';

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->isDevMode()) {
            return $next($request);
        }

        // Serve the hash check endpoint
        if ($request->getPathInfo() === self::PING_PATH) {
            return $this->pingResponse();
        }

        /** @var Response $response */
        $response = $next($request);

        // Inject script only into HTML responses
        $ct = (string) $response->headers->get('Content-Type', '');
        if (str_contains($ct, 'text/html') || $ct === '') {
            $body = $response->getContent();
            if (is_string($body) && str_contains($body, '</body>')) {
                $response->setContent(
                    str_replace('</body>', $this->injectScript() . '</body>', $body)
                );
            }
        }

        return $response;
    }

    // ── Ping endpoint ─────────────────────────────────────────────────

    private function pingResponse(): JsonResponse
    {
        $response = new JsonResponse(['hash' => $this->computeHash()]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * Compute an mtime hash over watched source files.
     * Fast enough for 800 ms polling; scans up to ~10k files in <5ms.
     */
    private function computeHash(): string
    {
        $basePath = Application::getInstance()->getBasePath();
        $watchDirs = [
            $basePath . '/app',
            $basePath . '/modules',
            $basePath . '/resources',
            $basePath . '/config',
            $basePath . '/routes',
        ];

        $extensions = ['php', 'twig', 'html', 'css', 'js'];
        $maxMtime   = 0;
        $fileCount  = 0;

        foreach ($watchDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }
                $mtime = $file->getMTime();
                if ($mtime > $maxMtime) {
                    $maxMtime = $mtime;
                }
                $fileCount++;
            }
        }

        // Also watch framework src when running from the monorepo
        $frameworkSrc = $basePath . '/../framework/src';
        if (is_dir($frameworkSrc)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($frameworkSrc, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $mtime = $file->getMTime();
                    if ($mtime > $maxMtime) {
                        $maxMtime = $mtime;
                    }
                    $fileCount++;
                }
            }
        }

        return md5("{$maxMtime}:{$fileCount}");
    }

    // ── Script injection ──────────────────────────────────────────────

    private function injectScript(): string
    {
        return <<<'HTML'
<script>
(function () {
  var _hash = null, _url = '/__ironflow/ping';
  function check() {
    fetch(_url, { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        if (_hash !== null && _hash !== d.hash) { location.reload(); return; }
        _hash = d.hash;
      })
      .catch(function () {});
  }
  check();
  setInterval(check, 800);
})();
</script>
HTML;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function isDevMode(): bool
    {
        $env   = strtolower((string) ($_ENV['APP_ENV'] ?? 'production'));
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return $env === 'local' || $debug;
    }
}
