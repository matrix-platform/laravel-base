<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MatrixPlatform\Columns\Declarations\Definition;

class Subject {

    public function __construct(private MetadataRegistry $registry) {}

    public function alias(Model $model): string {
        return $this->metadata($model)->alias;
    }

    public function foreign(Model $model): ?string {
        $relation = $this->parent($model);

        return $relation === null ? null : $relation->getForeignKeyName();
    }

    public function generic(string $prefix): ?string {
        $replaced = preg_replace('/\{[^}]+\}/u', '{id}', $prefix);

        return is_string($replaced) ? $replaced : null;
    }

    /**
     * @param array<string, mixed>|Model $source
     * @return list<Model>
     */
    public function parents(Model $model, array|Model $source): array {
        $parents = [];
        $visited = [];

        while (true) {
            $relation = $this->parent($model);

            if ($relation === null) {
                break;
            }

            $foreign = $relation->getForeignKeyName();
            $id = $source instanceof Model ? $source->getAttribute($foreign) : array_get_value($source, $foreign);
            $parent = $id === null ? null : $relation->getRelated()->newQuery()->find($id);

            if (!$parent instanceof Model) {
                break;
            }

            $key = $parent::class . ':' . strval($parent->getKey());

            if (array_key_exists($key, $visited)) {
                break;
            }

            $visited[$key] = true;
            $model = $parent;
            $source = $parent;
            $parents[] = $parent;
        }

        return $parents;
    }

    public function prefix(Model $model): string {
        return $this->path($model, []);
    }

    public function title(Model $model): ?string {
        $field = $this->metadata($model)->title;
        $definitions = $this->registry->definitions($model::class);
        $definition = $definitions === null ? null : array_get_value($definitions, $field);
        $translatable = $definition instanceof Definition && $definition->translatable;
        $value = $model->getAttribute($translatable ? "{$field}__" . app()->getLocale() : $field);

        return is_scalar($value) ? strval($value) : null;
    }

    private function metadata(Model $model): Metadata {
        $metadata = $this->registry->of($model::class);

        if ($metadata === null) {
            error('undeclared-model');
        }

        return $metadata;
    }

    /**
     * @return BelongsTo<Model, Model>|null
     */
    private function parent(Model $model): ?BelongsTo {
        $metadata = $this->metadata($model);

        return $metadata->parent === null ? null : $this->relation($model, $metadata->parent);
    }

    /**
     * @param array<string, bool> $visited
     */
    private function path(Model $model, array $visited): string {
        $metadata = $this->metadata($model);

        if ($metadata->parent === null) {
            return $metadata->alias;
        }

        $relation = $this->relation($model, $metadata->parent);
        $parent = $relation->getRelated();

        if ($parent instanceof $model || array_key_exists($parent::class, $visited)) {
            return $metadata->alias;
        }

        $visited[$model::class] = true;

        return $this->path($parent, $visited) . "/{{$relation->getForeignKeyName()}}/{$metadata->alias}";
    }

    /**
     * @return BelongsTo<Model, Model>
     */
    private function relation(Model $model, string $name): BelongsTo {
        $relation = $model->isRelation($name) ? $model->{$name}() : null;

        if (!$relation instanceof BelongsTo || $relation instanceof MorphTo) {
            error('invalid-parent-relation');
        }

        return $relation;
    }

}
