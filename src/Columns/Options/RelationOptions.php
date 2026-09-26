<?php //>

namespace MatrixPlatform\Columns\Options;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MatrixPlatform\Models\Builders\BaseBuilder;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Subject;

class RelationOptions implements OptionProvider {

    /**
     * @param class-string<Model> $related
     */
    public function __construct(private string $related, private bool $active = true) {}

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null, bool $trashed = false): array {
        return $this->tree($this->collect($trashed), '');
    }

    /**
     * @return array<string, list<array{class-string<Model>, Option}>>
     */
    private function collect(bool $trashed): array {
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
            $owner = $relation === null ? null : $relation->getRelated()::class;

            $query = $current::query();

            if ($trashed) {
                $query->withoutGlobalScope(SoftDeletingScope::class);
            } elseif ($this->active && $query instanceof BaseBuilder && $metadata->enable !== null && $metadata->disable !== null) {
                $query->whereActive($metadata->enable, $metadata->disable);
            }

            $selectable = $current::class === $this->related;

            foreach ($query->get() as $item) {
                $mapping[$this->key($owner, $foreign === null ? null : $item->getAttribute($foreign))][] = [$item::class, $this->option($subject, $item, $selectable)];
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

    private function deleted(Model $item): bool {
        return method_exists($item, 'trashed') && $item->trashed();
    }

    private function identifier(Model $item): int|string {
        $key = $item->getKey();

        return is_int($key) || is_string($key) ? $key : '';
    }

    private function key(?string $owner, mixed $value): string {
        return $owner === null || $value === null ? '' : $owner . '#' . $value;
    }

    private function option(Subject $subject, Model $item, bool $selectable): Option {
        $label = $subject->title($item);
        $ranking = $item->getAttribute('ranking');

        return new Option([], $this->identifier($item), is_int($ranking) ? $ranking : 0, is_string($label) ? $label : '', $this->deleted($item), $selectable);
    }

    /**
     * @param array<string, list<array{class-string<Model>, Option}>> $mapping
     * @return list<Option>
     */
    private function tree(array $mapping, string $key): array {
        $nodes = [];

        foreach (array_get_value($mapping, $key, []) as [$class, $node]) {
            $nodes[] = new Option($this->tree($mapping, $this->key($class, $node->id)), $node->id, $node->ranking, $node->title, $node->deleted, $node->selectable);
        }

        return $nodes;
    }

}
