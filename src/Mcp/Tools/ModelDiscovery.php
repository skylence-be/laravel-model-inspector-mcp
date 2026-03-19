<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Mcp\Tools;

use Composer\Autoload\ClassLoader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;
use Throwable;

/**
 * Discover all Eloquent models in the application and vendor packages.
 */
final class ModelDiscovery extends Tool
{
    protected string $name = 'eloquent-list-models';

    protected string $description = 'List all Eloquent models in the Laravel application and installed packages. Returns each model\'s fully qualified class name, database table, and relationship count. Use this to explore the data model or find a specific model by name. Auto-discovers models from all Composer PSR-4 autoload paths.';

    /** @var array<int, array{class: string, table: string, relationships: int}>|null */
    private ?array $cachedModels = null;

    /** @var array<int, string>|null */
    private ?array $cachedPaths = null;

    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Optional filter — only return models whose class name contains this string (case-insensitive). Example: "Order" returns all order-related models.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $params = $request->all();
        $filter = $params['filter'] ?? null;

        if ($this->cachedModels === null) {
            $this->cachedPaths = $this->discoverModelPaths();
            $this->cachedModels = [];

            foreach ($this->cachedPaths as $path) {
                if (! is_dir($path)) {
                    continue;
                }

                $this->cachedModels = array_merge($this->cachedModels, $this->scanDirectory($path));
            }

            // Deduplicate by class name
            $seen = [];
            $this->cachedModels = array_values(array_filter($this->cachedModels, function (array $m) use (&$seen): bool {
                if (isset($seen[$m['class']])) {
                    return false;
                }
                $seen[$m['class']] = true;

                return true;
            }));

            // Sort by class name
            usort($this->cachedModels, fn (array $a, array $b): int => strcmp($a['class'], $b['class']));
        }

        $models = $this->cachedModels;

        // Apply filter
        if ($filter) {
            $filter = mb_strtolower($filter);
            $models = array_values(array_filter($models, fn (array $m): bool => str_contains(mb_strtolower($m['class']), $filter)));
        }

        return Response::json([
            'total' => count($models),
            'scan_paths' => $this->cachedPaths,
            'models' => $models,
        ]);
    }

    /**
     * Auto-discover model paths from Composer's PSR-4 autoload map.
     *
     * Scans all registered PSR-4 prefixes and returns directories whose
     * namespace contains "Models". This covers app/Models, vendor package
     * models, and any custom autoload paths — no config needed.
     *
     * @return array<int, string>
     */
    private function discoverModelPaths(): array
    {
        $paths = [];

        $loader = $this->getComposerClassLoader();

        if ($loader === null) {
            return [base_path('app/Models')];
        }

        foreach ($loader->getPrefixesPsr4() as $namespace => $directories) {
            foreach ($directories as $directory) {
                $realDir = realpath($directory);

                if ($realDir === false) {
                    continue;
                }

                // If the namespace itself contains Models\, scan it directly
                if (str_contains($namespace, 'Models\\')) {
                    $paths[] = $realDir;

                    continue;
                }

                // Otherwise check if a Models subdirectory exists
                $modelsDir = $realDir.DIRECTORY_SEPARATOR.'Models';

                if (is_dir($modelsDir)) {
                    $paths[] = $modelsDir;
                }
            }
        }

        // Always include app/Models as a fallback
        $appModels = realpath(base_path('app/Models'));

        if ($appModels !== false && ! in_array($appModels, $paths, true)) {
            array_unshift($paths, $appModels);
        }

        // Merge extra paths from config (for non-standard locations)
        foreach (config('eloquent-mcp.extra_model_paths', []) as $extra) {
            $extraPath = realpath(base_path($extra));

            if ($extraPath !== false && ! in_array($extraPath, $paths, true)) {
                $paths[] = $extraPath;
            }
        }

        return $paths;
    }

    private function getComposerClassLoader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
                return $autoloader[0];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{class: string, table: string, relationships: int}>
     */
    private function scanDirectory(string $absolutePath): array
    {
        $models = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->resolveClassName($file->getPathname());

            if ($className === null || ! class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);

                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                $instance = $className::newModelInstance();
                $relationCount = $this->countRelationships($reflection);

                $models[] = [
                    'class' => $className,
                    'table' => $instance->getTable(),
                    'relationships' => $relationCount,
                ];
            } catch (Throwable $e) {
                Log::debug("Eloquent MCP: skipped {$className}: {$e->getMessage()}");
            }
        }

        return $models;
    }

    private function resolveClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath, length: 2048);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/^namespace\s+(.+?);/m', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/^(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($namespace === null || $class === null) {
            return null;
        }

        return $namespace.'\\'.$class;
    }

    private function countRelationships(ReflectionClass $reflection): int
    {
        $count = 0;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            if (is_subclass_of($returnType->getName(), \Illuminate\Database\Eloquent\Relations\Relation::class)) {
                $count++;
            }
        }

        return $count;
    }
}
