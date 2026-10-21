<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Schedule;

class ArrangeService extends CrudService {

    private bool $descending = false;

    private string $disable;

    private string $enable;

    private ?Closure $fixed = null;

    private bool $rankable = false;

    private string $ranking;

    public function __construct(string $model) {
        parent::__construct($model);

        $metadata = app(MetadataRegistry::class)->of($model);

        $this->disable = $this->field($metadata?->disable, 'disable_time');
        $this->enable = $this->field($metadata?->enable, 'enable_time');
        $this->ranking = $this->field($metadata?->ranking, 'ranking');
    }

    public function descending(bool $descending): static {
        $this->descending = $descending;

        return $this;
    }

    public function fixed(Closure $fixed): static {
        $this->fixed = $fixed;

        return $this;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, sortable: bool}
     */
    public function items(): array {
        $rows = [];

        foreach ($this->ordered() as $model) {
            $rows[] = [
                'id' => $model->getKey(),
                'title' => $this->subject->title($model),
                'enabled' => $this->enabled($model),
                'fixed' => $this->fixed !== null && ($this->fixed)($model) === true
            ];
        }

        return ['rows' => $rows, 'sortable' => $this->rankable];
    }

    public function rankable(bool $rankable): static {
        $this->rankable = $rankable;

        return $this;
    }

    /**
     * @return array{id: list<string>}
     */
    public function save(mixed $input): array {
        $values = is_array($input) ? $input : [];
        $raw = Arr::wrap(array_get_value($values, 'enabled'));

        if (!$this->scalars($raw)) {
            error('invalid-arrange-order');
        }

        $order = array_values(array_map(strval(...), $raw));
        $models = $this->keyed($this->ordered());

        if (array_diff($order, array_keys($models)) !== [] || count($order) !== count(array_unique($order))) {
            error('invalid-arrange-order');
        }

        if ($this->rankable) {
            $ascending = $this->descending ? array_reverse($order) : $order;
            $rankings = $this->reassignRankings($models, $this->ranking, $ascending);

            foreach ($ascending as $index => $id) {
                $models[$id]->setAttribute($this->ranking, $rankings[$index]);
            }
        }

        $enabled = array_flip($order);

        foreach ($models as $id => $model) {
            $this->apply($model, isset($enabled[$id]));

            $this->inspect($model);

            $model->save();
        }

        return ['id' => $order];
    }

    private function apply(Model $model, bool $enable): void {
        if ($enable === $this->enabled($model)) {
            return;
        }

        if ($enable) {
            $model->setAttribute($this->enable, now());

            $disable = $model->getAttribute($this->disable);

            if ($disable !== null && !Schedule::future($disable)) {
                $model->setAttribute($this->disable, null);
            }
        } else {
            $model->setAttribute($this->disable, now());
        }
    }

    private function enabled(Model $model): bool {
        return Schedule::isEnabled($model, $this->enable, $this->disable);
    }

    private function field(?string $value, string $default): string {
        return $value === null ? $default : $value;
    }

    /**
     * @return Collection<int, BaseModel>
     */
    private function ordered(): Collection {
        $query = $this->plain();

        if ($this->rankable) {
            $query->orderBy($this->ranking, $this->descending ? 'desc' : 'asc');
        }

        return $query->orderBy($this->model->getKeyName())->get();
    }

}
