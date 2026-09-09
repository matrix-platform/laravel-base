<?php //>

namespace MatrixPlatform\Services\Admin\Shared;

use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Operator;

class ManipulationLogService {

    public function __construct(private SharedModelResolver $resolver) {}

    /**
     * @return array<string, mixed>
     */
    public function query(string $prefix, int $id, int $page, int $size): array {
        $class = $this->resolver->permit($prefix, 'query');
        $table = (new $class())->getTable();

        $query = ManipulationLog::query()
            ->where('data_type', $table)
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

        return ['rows' => $rows, 'pagination' => ['page' => $page, 'size' => $size, 'total' => $total]];
    }

}
