<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Mcp\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Inspect the database schema for a model's table — columns, indexes, and foreign keys.
 */
final class SchemaInspector extends Tool
{
    protected string $name = 'eloquent-get-table-schema';

    protected string $description = 'Get the database table schema for an Eloquent model or raw table name. Returns columns (name, type, nullable, default), indexes (columns, unique, primary), and foreign keys (referenced table, columns, on_delete/on_update). Use this to understand the database structure behind a model.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'target' => $schema->string()
                ->description('Fully qualified model class name (e.g. App\\Models\\User) or database table name (e.g. users)')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $params = $request->all();
        $target = $params['target'] ?? null;

        if (! $target) {
            return Response::json([
                'error' => true,
                'message' => 'No target provided.',
            ]);
        }

        // Resolve table name from model class or use as-is
        if (class_exists($target) && is_subclass_of($target, Model::class)) {
            $instance = $target::newModelInstance();
            $table = $instance->getTable();
            $connection = $instance->getConnectionName();
        } else {
            // Validate table name to prevent unexpected input
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $target)) {
                return Response::json([
                    'error' => true,
                    'message' => sprintf('Invalid table name "%s".', $target),
                ]);
            }

            $table = $target;
            $connection = null;
        }

        $schema = Schema::connection($connection);

        if (! $schema->hasTable($table)) {
            return Response::json([
                'error' => true,
                'message' => sprintf('Table "%s" does not exist.', $table),
            ]);
        }

        $columns = collect($schema->getColumns($table))->map(fn (array $column): array => [
            'name' => $column['name'],
            'type' => $column['type'],
            'nullable' => $column['nullable'],
            'default' => $column['default'],
        ])->toArray();

        $indexes = collect($schema->getIndexes($table))->map(fn (array $index): array => [
            'name' => $index['name'],
            'columns' => $index['columns'],
            'unique' => $index['unique'],
            'primary' => $index['primary'] ?? false,
        ])->toArray();

        $foreignKeys = collect($schema->getForeignKeys($table))->map(fn (array $fk): array => [
            'name' => $fk['name'],
            'columns' => $fk['columns'],
            'foreign_table' => $fk['foreign_table'],
            'foreign_columns' => $fk['foreign_columns'],
            'on_update' => $fk['on_update'] ?? null,
            'on_delete' => $fk['on_delete'] ?? null,
        ])->toArray();

        return Response::json([
            'table' => $table,
            'connection' => $connection ?? config('database.default'),
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ]);
    }
}
