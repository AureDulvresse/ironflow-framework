<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller {name?} {--module=} {--resource} {--api}';
    protected string $description = 'Create a new controller class';

    protected function handle(): int
    {
        $name = $this->argumentOrAsk('name', 'Controller name (e.g. PostController):');
        $rawModule = $this->option('module');
        $module = is_string($rawModule) && $rawModule !== '' ? ucfirst($rawModule) : null;
        $resource = (bool) $this->option('resource');
        $api = (bool) $this->option('api');

        if ($module) {
            $path = base_path("modules/{$module}/Controllers/{$name}.php");
            $ns = "Modules\\{$module}\\Controllers";
        } else {
            $path = base_path("app/Controllers/{$name}.php");
            $ns = "App\\Controllers";
            @mkdir(base_path('app/Controllers'), 0755, true);
        }

        if (is_file($path)) {
            $this->error("Controller [{$name}] already exists.");
            return self::FAILURE;
        }

        $methods = $resource ? $this->resourceMethods() : $this->basicMethods();
        $content = $this->stub($ns, $name, $methods, $api);

        file_put_contents($path, $content);
        $this->success("Controller [{$name}] created.");

        if ($module) {
            $this->registerAsProvider($module, "{$ns}\\{$name}");
        }

        return self::SUCCESS;
    }

    /**
     * A controller is only protected by module isolation once it's declared
     * in its module's `providers: [...]`  — this is opt-in by design, not
     * auto-discovered by namespace convention. Insert it there automatically
     * so a freshly generated controller is protected without a manual step.
     */
    private function registerAsProvider(string $module, string $controllerFqcn): void
    {
        $modulePath = base_path("modules/{$module}/{$module}Module.php");

        if (!is_file($modulePath)) {
            $this->line("Note: could not find {$modulePath} — add \\{$controllerFqcn}::class to its providers: manually.");
            return;
        }

        $source = file_get_contents($modulePath);
        $updated = $this->insertIntoProviders($source, $controllerFqcn);

        if ($updated === null) {
            $this->line("Note: could not locate providers: in {$module}Module.php — add \\{$controllerFqcn}::class manually.");
            return;
        }

        if ($updated !== $source) {
            file_put_contents($modulePath, $updated);
        }
    }

    /**
     * Inserts "\Fqcn::class" into the providers: [...] array of a #[Module(...)]
     * attribute. Returns null if no providers: array could be found (caller
     * falls back to a manual instruction rather than risk corrupting the
     * file). Idempotent — does nothing if the FQCN is already listed.
     */
    private function insertIntoProviders(string $source, string $fqcn): ?string
    {
        if (!preg_match('/providers\s*:\s*\[/', $source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $openBracket = $match[0][1] + strlen($match[0][0]) - 1;
        $depth = 0;
        $closeBracket = null;

        for ($i = $openBracket; $i < strlen($source); $i++) {
            if ($source[$i] === '[') {
                $depth++;
            } elseif ($source[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    $closeBracket = $i;
                    break;
                }
            }
        }

        if ($closeBracket === null) {
            return null;
        }

        $inner = substr($source, $openBracket + 1, $closeBracket - $openBracket - 1);
        $entry = "\\{$fqcn}::class";

        if (str_contains($inner, $entry)) {
            return $source;
        }

        $trimmedInner = trim($inner);
        $newInner = $trimmedInner === ''
            ? "\n        {$entry},\n    "
            : rtrim($inner) . (str_ends_with(rtrim($inner), ',') ? '' : ',') . "\n        {$entry},\n    ";

        return substr($source, 0, $openBracket + 1) . $newInner . substr($source, $closeBracket);
    }

    private function stub(string $ns, string $name, string $methods, bool $api): string
    {
        $base    = $api ? 'ApiController' : 'Controller';
        $useBase = "use Ironflow\\Http\\{$base};";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Ironflow\\Http\\Request;
use Ironflow\\Http\\Response;
{$useBase}

class {$name} extends {$base}
{
{$methods}
}
PHP;
    }

    private function basicMethods(): string
    {
        return <<<PHP
    public function index(Request \$request): Response
    {
        return Response::view('index', []);
    }
PHP;
    }

    private function resourceMethods(): string
    {
        return <<<'PHP'
    public function index(Request $request): Response
    {
        return Response::view('index', []);
    }

    public function create(Request $request): Response
    {
        return Response::view('create', []);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([]);
        // store...
        return Response::redirect('/')->route('index');
    }

    public function show(Request $request, int $id): Response
    {
        return Response::view('show', ['id' => $id]);
    }

    public function edit(Request $request, int $id): Response
    {
        return Response::view('edit', ['id' => $id]);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->validate([]);
        // update...
        return Response::redirect('/')->route('index');
    }

    public function destroy(Request $request, int $id): Response
    {
        // delete...
        return Response::redirect('/')->route('index');
    }
PHP;
    }
}
