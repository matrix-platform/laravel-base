<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Support\MetadataRegistry;

class CopyService extends CrudService {

    /**
     * @var list<string>
     */
    private array $cascade = [];

    /**
     * @param list<string> $relations
     */
    public function cascade(array $relations): static {
        $this->cascade = $relations;

        return $this;
    }

    /**
     * @return array{id: mixed}
     */
    public function copy(int|string $id): array {
        $source = $this->plain()->findOrFail($id);
        $copy = $source->replicate();

        $this->reset($copy);
        $this->inspect($copy, $source);

        $copy->save();

        foreach ($this->cascade as $relation) {
            $this->propagate($source, $copy, explode('.', $relation));
        }

        return ['id' => $copy->getKey()];
    }

    /**
     * @param list<string> $chain
     */
    private function propagate(Model $source, Model $copy, array $chain): void {
        $name = array_shift($chain);

        if ($name === null) {
            return;
        }

        $relation = $this->cascading($source, $name);
        $foreign = $relation->getForeignKeyName();

        foreach ($relation->get() as $child) {
            $clone = $child->replicate();

            $clone->setAttribute($foreign, $copy->getKey());

            $this->inspect($clone, $child);

            $clone->save();

            $this->propagate($child, $clone, $chain);
        }
    }

    private function reset(Model $copy): void {
        $metadata = app(MetadataRegistry::class)->of($copy::class);

        foreach ([$metadata?->enable, $metadata?->disable] as $column) {
            if ($column !== null) {
                $copy->setAttribute($column, null);
            }
        }

        if ($metadata?->ranking !== null) {
            $copy->offsetUnset($metadata->ranking);
        }
    }

}
