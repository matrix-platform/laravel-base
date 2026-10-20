<?php //>

namespace MatrixPlatform\Services\Admin\Shared;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\SharedModels;
use MatrixPlatform\Support\Subject;
use ReflectionClass;

class SharedModelResolver {

    public function __construct(private SharedModels $models, private Menus $menus) {}

    public function belong(string $prefix, Model $model): void {
        $subject = app(Subject::class);

        if (!$subject->recursive($model)) {
            return;
        }

        $foreign = $subject->foreign($model);
        $root = $foreign === null || $model->getAttribute($foreign) === null;

        if ($root !== $this->standalone($prefix)) {
            error('data-not-found', 404);
        }
    }

    /**
     * @return class-string<Model>
     */
    public function permit(string $prefix, string $tag): string {
        $class = $this->models->resolve($prefix);

        if ($class === null) {
            invalid('prefix', 'unsupported-model');
        }

        if (!$this->registered($prefix, $tag) || !app(AdminPermission::class)->permits($prefix, $tag)) {
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

        $this->belong($prefix, $model);

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

    private function standalone(string $prefix): bool {
        $controller = ActionRoutes::controller($prefix);

        if ($controller === null || !class_exists($controller)) {
            return false;
        }

        return array_get_value((new ReflectionClass($controller))->getDefaultProperties(), 'standalone') === true;
    }

}
