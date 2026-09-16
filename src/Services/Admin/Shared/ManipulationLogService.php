<?php //>

namespace MatrixPlatform\Services\Admin\Shared;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Operator;
use MatrixPlatform\Support\MetadataRegistry;

class ManipulationLogService {

    public function __construct(private SharedModelResolver $resolver, private ColumnResolver $columns, private MetadataRegistry $registry) {}

    /**
     * @return array<string, mixed>
     */
    public function query(string $prefix, int $id, int $page, int $size): array {
        $class = $this->resolver->permit($prefix, 'query');
        $model = new $class();

        $query = ManipulationLog::query()
            ->where('data_type', $model->getTable())
            ->where('data_id', $id)
            ->orderByDesc('id');

        $total = $query->count();
        $logs = $query->forPage($page, $size)->get();
        $creators = Operator::identities($logs->pluck('creator_id'));

        $rows = $logs->map(function (ManipulationLog $log) use ($creators): array {
            $creator = $log->creator_id === null ? null : array_get_value($creators, $log->creator_id);

            return [
                'id' => $log->id,
                'type' => $log->type->name,
                'endpoint' => $log->endpoint,
                'ip' => $log->ip,
                'before' => $log->before,
                'after' => $log->after,
                'creator_id' => $log->creator_id,
                'creator' => $creator?->username,
                'creator_type' => $creator?->type->name,
                'create_time' => $log->create_time->format(config('matrix.datetime-format'))
            ];
        });

        return ['rows' => $rows, 'options' => $this->options($model), 'pagination' => ['page' => $page, 'size' => $size, 'total' => $total]];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Model $model): array {
        $definitions = $this->registry->definitions($model::class);

        if ($definitions === null) {
            error('undeclared-model');
        }

        $parser = new ColumnParser();
        $options = [];

        foreach (array_keys($definitions) as $name) {
            $provider = $this->columns->resolve($parser->parse($name), $model)->options;

            if ($provider !== null) {
                $options[$name] = $provider->options(null, true);
            }
        }

        return $options;
    }

}
