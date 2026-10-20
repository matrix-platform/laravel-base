<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\BlockItemTypeResolver;
use MatrixPlatform\Columns\Declarations\BlockTypeResolver;
use MatrixPlatform\Columns\Declarations\TypeResolver;
use MatrixPlatform\Columns\Declarations\Variant;

class Variants {

    private const DEFAULT_RESOLVERS = [
        'block-data' => BlockTypeResolver::class,
        'block-item-data' => BlockItemTypeResolver::class
    ];

    /**
     * @var array<string, ?TypeResolver>
     */
    private array $resolvers = [];

    /**
     * @var array<string, ?Variant>
     */
    private array $variants = [];

    public function of(string $group, ?string $type): ?Variant {
        if ($type === null) {
            return null;
        }

        $key = "{$group}.{$type}";

        if (!array_key_exists($key, $this->variants)) {
            $class = config("matrix.variants.{$group}.{$type}");

            $this->variants[$key] = is_string($class) && is_a($class, Variant::class, true) ? app($class) : null;
        }

        return $this->variants[$key];
    }

    public function type(string $group, ?Model $model, mixed $input): ?string {
        return $this->resolver($group)?->resolve($model, $input);
    }

    public function variant(string $group, ?Model $model, mixed $input): ?Variant {
        return $this->of($group, $this->type($group, $model, $input));
    }

    private function resolver(string $group): ?TypeResolver {
        if (array_key_exists($group, $this->resolvers)) {
            return $this->resolvers[$group];
        }

        $driver = config("matrix.variants.{$group}.driver");

        if ($driver === null && array_key_exists($group, self::DEFAULT_RESOLVERS)) {
            $driver = self::DEFAULT_RESOLVERS[$group];
        }

        if ($driver !== null && (!is_string($driver) || !is_a($driver, TypeResolver::class, true))) {
            error('invalid-type-resolver');
        }

        $this->resolvers[$group] = $driver === null ? null : app($driver);

        return $this->resolvers[$group];
    }

}
