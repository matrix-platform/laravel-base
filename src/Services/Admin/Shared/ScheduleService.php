<?php //>

namespace MatrixPlatform\Services\Admin\Shared;

use MatrixPlatform\Support\MetadataRegistry;

class ScheduleService {

    public function __construct(private SharedModelResolver $resolver) {}

    /**
     * @return array<string, mixed>
     */
    public function toggle(string $prefix, int $id, bool $enabled): array {
        $model = $this->resolver->resolve($prefix, $id, 'update');
        $metadata = app(MetadataRegistry::class)->of($model::class);

        if ($metadata === null || $metadata->enable === null || $metadata->disable === null) {
            invalid('prefix', 'schedule-not-supported');
        }

        if ($enabled) {
            $model->setAttribute($metadata->enable, now());
            $model->setAttribute($metadata->disable, null);
        } else {
            $model->setAttribute($metadata->disable, now());
        }

        $model->save();

        return ['enabled' => $enabled];
    }

}
