<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Routing\ActionRoutes;
use ReflectionClass;

class SharedModels {

    /**
     * @return class-string<Model>|null
     */
    public function resolve(string $prefix): ?string {
        $controller = ActionRoutes::controller($prefix);

        if ($controller === null || !class_exists($controller)) {
            return null;
        }

        $model = array_get_value((new ReflectionClass($controller))->getDefaultProperties(), 'model');

        return is_string($model) && is_a($model, Model::class, true) ? $model : null;
    }

}
