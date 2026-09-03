<?php //>

namespace MatrixPlatform\Columns\Options;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Subject;

class RelationOptions implements OptionProvider {

    /**
     * @param class-string<Model> $related
     */
    public function __construct(private string $related) {}

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null): array {
        return $this->tree($this->collect(), null);
    }

    /**
     * @return array<string, list<Option>>
     */
    private function collect(): array {
        $current = new $this->related();
        $mapping = [];
        $registry = app(MetadataRegistry::class);
        $subject = app(Subject::class);

        while (true) {
            $metadata = $registry->of($current::class);

            if ($metadata === null) {
                error('undeclared-model');
            }

            $parent = $metadata->parent;
            $relation = $parent === null ? null : $current->{$parent}();
            $foreign = $relation === null ? null : $relation->getForeignKeyName();

            foreach ($current::query()->get() as $item) {
                $mapping[$this->key($foreign === null ? null : $item->getAttribute($foreign))][] = $this->option($subject, $item);
            }

            if ($relation === null) {
                break;
            }

            $next = $relation->getRelated();

            if ($next::class === $current::class) {
                break;
            }

            $current = $next;
        }

        return $mapping;
    }

    private function identifier(Model $item): int|string {
        $key = $item->getKey();

        return is_int($key) || is_string($key) ? $key : '';
    }

    private function key(mixed $value): string {
        return $value === null ? '' : (string) $value;
    }

    private function option(Subject $subject, Model $item): Option {
        $label = $subject->title($item);
        $ranking = $item->getAttribute('ranking');

        return new Option([], $this->identifier($item), is_int($ranking) ? $ranking : 0, is_string($label) ? $label : '');
    }

    /**
     * @param array<string, list<Option>> $mapping
     * @return list<Option>
     */
    private function tree(array $mapping, int|string|null $id): array {
        $nodes = [];

        foreach (array_get_value($mapping, $this->key($id), []) as $node) {
            $nodes[] = new Option($this->tree($mapping, $node->id), $node->id, $node->ranking, $node->title);
        }

        return $nodes;
    }

}
