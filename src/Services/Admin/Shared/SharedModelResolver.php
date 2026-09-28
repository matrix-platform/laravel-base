<?php //>

namespace MatrixPlatform\Services\Admin\Shared;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\SharedModels;
use MatrixPlatform\Support\Subject;

class SharedModelResolver {

    public function __construct(private SharedModels $models, private Menus $menus) {}

    /**
     * @return class-string<Model>
     */
    public function permit(string $prefix, string $tag): string {
        $class = $this->models->resolve($prefix);

        if ($class === null) {
            invalid('prefix', 'unsupported-model');
        }

        $path = app(Subject::class)->prefix(new $class());

        if (!$this->registered($path, $tag) || !app(AdminPermission::class)->permits($path, $tag)) {
            error('permission-denied', 403);
        }

        return $class;
    }

    public function resolve(string $prefix, int $id, string $tag): Model {
        $class = $this->permit($prefix, $tag);
        $model = $class::query()->find($id);

        if ($model === null) {
            error('data-not-found', 404);
        }

        return $model;
    }

    private function registered(string $path, string $tag): bool {
        foreach ($this->menus->nodes() as $node) {
            if (($node->path === $path || $node->parent === $path) && $node->tag === $tag) {
                return true;
            }
        }

        return false;
    }

}
