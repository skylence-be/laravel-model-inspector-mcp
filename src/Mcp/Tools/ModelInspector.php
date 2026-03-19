<?php

declare(strict_types=1);

namespace Skylence\ModelInspectorMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Inspect an Eloquent model's attributes, relationships, casts, scopes, and observers.
 */
final class ModelInspector extends Tool
{
    protected string $name = 'eloquent-inspect-model';

    protected string $description = 'Get detailed information about a specific Eloquent model: its database columns (with types, nullability, defaults), fillable/guarded attributes, casts, relationships, scopes, observers, and associated policy. Use after eloquent-list-models to drill into a specific model.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Fully qualified model class name, e.g. App\\Models\\User or Skylence\\Erp\\Models\\Sales\\Order')
                ->required(),
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

        if (! is_subclass_of($model, Model::class)) {
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
