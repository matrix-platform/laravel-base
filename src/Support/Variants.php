<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\TypeResolver;
use MatrixPlatform\Columns\Declarations\Variant;

class Variants {

    /**
     * @var array<string, ?TypeResolver>
     */
    private array $resolvers = [];

    /**
     * @var array<string, ?Variant>
     */
    private array $variants = [];

    public function of(string $group, ?string $type): ?Variant {
        $key = "{$group}.{$type}";

        if ($type === null) {
            return null;
        }

        if (!array_key_exists($key, $this->variants)) {
            $class = config("matrix.variants.{$group}.{$type}");

            $this->variants[$key] = is_string($class) && is_a($class, Variant::class, true) ? app($class) : null;
        }

        return $this->variants[$key];
    }

    public function type(string $group, ?Model $model, mixed $input): ?string {
        $resolver = $this->resolver($group);

        return $resolver === null ? null : $resolver->resolve($model, $input);
    }

    public function variant(string $group, ?Model $model, mixed $input): ?Variant {
        return $this->of($group, $this->type($group, $model, $input));
    }

    private function resolver(string $group): ?TypeResolver {
        if (array_key_exists($group, $this->resolvers)) {
            return $this->resolvers[$group];
        }

        $driver = config("matrix.variants.{$group}.driver");

        if ($driver !== null && (!is_string($driver) || !is_a($driver, TypeResolver::class, true))) {
            error('invalid-type-resolver');
        }

        $this->resolvers[$group] = $driver === null ? null : app($driver);

        return $this->resolvers[$group];
    }

}
