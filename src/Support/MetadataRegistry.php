<?php //>

namespace MatrixPlatform\Support;

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
