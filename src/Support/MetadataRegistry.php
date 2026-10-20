<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use ReflectionClass;

class MetadataRegistry {

    /**
     * @var array<string, ?Declares>
     */
    private array $resolved = [];

    /**
     * @return array<class-string<Model>, Declares>
     */
    public function declaredModels(PackageRegistry $packages): array {
        $models = [];

        foreach ($packages->models() as $model) {
            if (!is_a($model, Model::class, true)) {
                continue;
            }

            $declares = $this->declares($model);

            if ($declares !== null) {
                $models[$model] = $declares;
            }
        }

        return $models;
    }

    /**
     * @return array<string, Definition>|null
     */
    public function definitions(string $model): ?array {
        return $this->declares($model)?->definitions();
    }

    public function of(string $model): ?Metadata {
        return $this->declares($model)?->metadata();
    }

    public function register(string $model, Declares $declares): void {
        $this->resolved[$model] = $declares;
    }

    /**
     * @return list<string>
     */
    public function unresolvedModels(PackageRegistry $packages): array {
        $names = [];

        foreach ($packages->models() as $name) {
            if (!class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function attribute(string $model): ?Declares {
        if (!class_exists($model)) {
            return null;
        }

        $attributes = (new ReflectionClass($model))->getAttributes(Declared::class);

        if ($attributes === []) {
            return null;
        }

        $declaration = $attributes[0]->newInstance()->declaration;

        return new $declaration();
    }

    private function declares(string $model): ?Declares {
        if (!array_key_exists($model, $this->resolved)) {
            $this->resolved[$model] = $this->attribute($model);
        }

        return $this->resolved[$model];
    }

}
