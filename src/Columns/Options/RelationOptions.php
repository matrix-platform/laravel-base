<?php //>

namespace MatrixPlatform\Columns\Options;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MatrixPlatform\Models\Builders\BaseBuilder;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Schedule;
use MatrixPlatform\Support\Subject;

class RelationOptions implements OptionProvider {

    /**
     * @param class-string<Model> $related
     */
    public function __construct(private string $related, private bool $active = true, private ?string $column = null) {}

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null, bool $trashed = false): array {
        $value = $model === null || $this->column === null ? null : $model->getAttribute($this->column);
        $selected = is_int($value) || is_string($value) ? strval($value) : null;

        return $this->tree($this->collect($trashed, $selected), '');
    }

    /**
     * @return array<string, list<array{class-string<Model>, Option, bool}>>
     */
    private function collect(bool $trashed, ?string $selected): array {
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
            $foreign = $relation?->getForeignKeyName();
            $owner = $relation === null ? null : $relation->getRelated()::class;

            $query = $current::query();
            $enable = $metadata->enable;
            $disable = $metadata->disable;
            $scheduled = !$trashed && $this->active && $query instanceof BaseBuilder && $enable !== null && $disable !== null;

            if ($trashed) {
                $query->withoutGlobalScope(SoftDeletingScope::class);
            }

            foreach ($query->get() as $item) {
                $active = !$scheduled || Schedule::isEnabled($item, strval($enable), strval($disable));
                $selectable = $current::class === $this->related && ($active || ($selected !== null && strval($this->identifier($item)) === $selected));

                $mapping[$this->key($owner, $foreign === null ? null : $item->getAttribute($foreign))][] = [$item::class, $this->option($subject, $item, $selectable), $active];
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
     * @param array<string, list<array{class-string<Model>, Option, bool}>> $mapping
     * @return list<Option>
     */
    private function tree(array $mapping, string $key): array {
        $nodes = [];

        foreach (array_get_value($mapping, $key, []) as [$class, $node, $active]) {
            $children = $this->tree($mapping, $this->key($class, $node->id));

            if ($active || $node->selectable || $children !== []) {
                $nodes[] = new Option($children, $node->id, $node->ranking, $node->title, $node->deleted, $node->selectable);
            }
        }

        return $nodes;
    }

}
