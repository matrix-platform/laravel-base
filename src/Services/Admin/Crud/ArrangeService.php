<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use MatrixPlatform\Support\MetadataRegistry;

class ArrangeService extends CrudService {

    private string $disable;

    private string $enable;

    private bool $rankable = false;

    private string $ranking;

    public function __construct(string $model) {
        parent::__construct($model);

        $metadata = app(MetadataRegistry::class)->of($model);

        $this->disable = $this->field($metadata?->disable, 'disable_time');
        $this->enable = $this->field($metadata?->enable, 'enable_time');
        $this->ranking = $this->field($metadata?->ranking, 'ranking');
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
                'enabled' => $this->enabled($model)
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
        $order = array_values(array_map(fn (mixed $id): string => strval($id), Arr::wrap(array_get_value($values, 'enabled'))));
        $models = $this->keyed($this->ordered());

        if (array_diff($order, array_keys($models)) !== [] || count($order) !== count(array_unique($order))) {
            error('invalid-arrange-order');
        }

        if ($this->rankable) {
            $rankings = $this->reassignRankings($models, $this->ranking, $order);

            foreach ($order as $index => $id) {
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
            $model->setAttribute($this->disable, null);
        } else {
            $model->setAttribute($this->disable, now());
        }
    }

    private function enabled(Model $model): bool {
        $enable = $model->getAttribute($this->enable);

        if ($enable === null || $this->future($enable)) {
            return false;
        }

        $disable = $model->getAttribute($this->disable);

        return $disable === null || $this->future($disable);
    }

    private function field(?string $value, string $default): string {
        return $value === null ? $default : $value;
    }

    private function future(mixed $value): bool {
        return ($value instanceof Carbon ? $value : Carbon::parse($value))->isFuture();
    }

    /**
     * @return Collection<int, Model>
     */
    private function ordered(): Collection {
        $query = $this->plain();

        if ($this->rankable) {
            $query->orderBy($this->ranking);
        }

        return $query->orderBy('id')->get();
    }

}
