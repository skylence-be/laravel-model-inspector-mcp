<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Inspect an Eloquent model's attributes, relationships, casts, scopes, and observers.
 * Works with any model class, including vendor package models.
 */
final class ModelInspector extends Tool
{
    protected string $description = 'Inspect an Eloquent model for attributes, relationships, casts, scopes, observers, and database schema. Accepts any fully qualified class name including vendor package models.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully qualified model class name, e.g. App\\Models\\User or Skylence\\Erp\\Models\\Sales\\Order'),
        ];
    }

    public function handle(Request $request): Response
    {
        $params = $request->all();
        $model = $params['model'] ?? null;

        if (! $model || ! class_exists($model)) {
            return Response::json([
                'error' => true,
                'message' => sprintf('Model class "%s" not found.', $model ?? 'null'),
            ]);
        }

        if (! is_subclass_of($model, \Illuminate\Database\Eloquent\Model::class)) {
            return Response::json([
                'error' => true,
                'message' => sprintf('Class "%s" is not an Eloquent model.', $model),
            ]);
        }

        Artisan::call('model:show', [
            'model' => $model,
            '--json' => true,
            '--no-interaction' => true,
        ]);

        $output = Artisan::output();
        $data = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return Response::json([
                'error' => true,
                'message' => 'Failed to parse model:show output.',
                'raw' => $output,
            ]);
        }

        return Response::json($data);
    }
}
