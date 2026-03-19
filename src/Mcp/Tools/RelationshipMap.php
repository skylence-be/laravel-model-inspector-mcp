<?php

declare(strict_types=1);

namespace Skylence\ModelInspectorMcp\Mcp\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Map relationships for one or more Eloquent models with their types,
 * related models, foreign keys, and cardinality.
 */
final class RelationshipMap extends Tool
{
    protected string $name = 'eloquent-get-relationships';

    protected string $description = 'Get the relationship map for one or more Eloquent models. Returns each relationship\'s name, type (HasMany, BelongsTo, MorphMany, BelongsToMany, etc.), related model class, database table, foreign keys, and pivot tables. Use this to understand how models connect to each other.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'models' => $schema->string()
                ->description('Comma-separated fully qualified model class names, e.g. "App\\Models\\User,Skylence\\Erp\\Models\\Sales\\Order"')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $params = $request->all();
        $input = $params['models'] ?? '';
        $classNames = array_filter(array_map('trim', explode(',', $input)));

        if ($classNames === []) {
            return Response::json([
                'error' => true,
                'message' => 'No model classes provided.',
            ]);
        }

        $result = [];

        foreach ($classNames as $className) {
            if (! class_exists($className)) {
                $result[$className] = ['error' => sprintf('Class "%s" not found.', $className)];

                continue;
            }

            if (! is_subclass_of($className, Model::class)) {
                $result[$className] = ['error' => sprintf('Class "%s" is not an Eloquent model.', $className)];

                continue;
            }

            $result[$className] = $this->extractRelationships($className);
        }

        return Response::json($result);
    }

    /**
     * @param  class-string<Model>  $className
     * @return array<string, array<string, mixed>>
     */
    private function extractRelationships(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $relationships = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $className) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $returnTypeName = $returnType->getName();
            if (! is_subclass_of($returnTypeName, Relation::class) && $returnTypeName !== Relation::class) {
                continue;
            }

            try {
                $instance = $className::newModelInstance();
                $relation = $method->invoke($instance);

                $info = [
                    'type' => class_basename($relation),
                    'related' => get_class($relation->getRelated()),
                    'related_table' => $relation->getRelated()->getTable(),
                ];

                if (method_exists($relation, 'getForeignKeyName')) {
                    $info['foreign_key'] = $relation->getForeignKeyName();
                }

                if (method_exists($relation, 'getOwnerKeyName')) {
                    $info['owner_key'] = $relation->getOwnerKeyName();
                }

                if (method_exists($relation, 'getLocalKeyName')) {
                    $info['local_key'] = $relation->getLocalKeyName();
                }

                if (method_exists($relation, 'getMorphType')) {
                    $info['morph_type'] = $relation->getMorphType();
                }

                if (method_exists($relation, 'getTable')) {
                    $pivotTable = $relation->getTable();
                    $relatedTable = $relation->getRelated()->getTable();
                    if ($pivotTable !== $relatedTable) {
                        $info['pivot_table'] = $pivotTable;
                    }
                }

                $relationships[$method->getName()] = $info;
            } catch (Throwable $e) {
                Log::debug("Model Inspector MCP: failed to resolve relationship {$className}::{$method->getName()}: {$e->getMessage()}");

                $relationships[$method->getName()] = [
                    'type' => class_basename($returnTypeName),
                    'error' => 'Could not resolve relationship at runtime.',
                ];
            }
        }

        return $relationships;
    }
}
