<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Mcp\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Discover all Eloquent models in the application and configured vendor paths.
 * Returns class names, table names, and relationship counts.
 */
final class ModelDiscovery extends Tool
{
    protected string $description = 'Discover all Eloquent models in the application and configured vendor packages. Returns class names, table names, and relationship counts. Configure additional scan paths in config/eloquent-mcp.php under model_paths.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Optional filter — only return models whose class name contains this string (case-insensitive)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $params = $request->all();
        $filter = $params['filter'] ?? null;

        $paths = config('eloquent-mcp.model_paths', ['app/Models']);
        $models = [];

        foreach ($paths as $path) {
            $absolutePath = base_path($path);

            if (! is_dir($absolutePath)) {
                continue;
            }

            $models = array_merge($models, $this->scanDirectory($absolutePath, $path));
        }

        // Apply filter
        if ($filter) {
            $filter = mb_strtolower($filter);
            $models = array_filter($models, fn (array $m): bool => str_contains(mb_strtolower($m['class']), $filter));
            $models = array_values($models);
        }

        // Sort by class name
        usort($models, fn (array $a, array $b): int => strcmp($a['class'], $b['class']));

        return Response::json([
            'total' => count($models),
            'scan_paths' => $paths,
            'models' => $models,
        ]);
    }

    /**
     * @return array<int, array{class: string, table: string, relationships: int}>
     */
    private function scanDirectory(string $absolutePath, string $configPath): array
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

            $className = $this->resolveClassName($file->getPathname(), $absolutePath, $configPath);

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
            } catch (Throwable) {
                // Skip models that can't be instantiated
            }
        }

        return $models;
    }

    private function resolveClassName(string $filePath, string $basePath, string $configPath): ?string
    {
        // Read the file to extract namespace and class name
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/^namespace\s+(.+?);/m', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $matches)) {
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

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof \ReflectionNamedType) {
                continue;
            }

            if (is_subclass_of($returnType->getName(), \Illuminate\Database\Eloquent\Relations\Relation::class)) {
                $count++;
            }
        }

        return $count;
    }
}
