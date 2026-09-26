<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\Sorting;
use MatrixPlatform\Support\ScheduleFilter;

class ExportService extends CrudService {

    /**
     * @var array<string, Closure>
     */
    private array $cells = [];

    /**
     * @var list<string|array<string, mixed>>
     */
    private array $filterColumns = [];

    /**
     * @var list<string>|null
     */
    private ?array $locales = null;

    /**
     * @var list<string|array<string, mixed>>
     */
    private array $optionals = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $options = [];

    private bool $selectable = false;

    /**
     * @var list<string>
     */
    private array $sorting = [];

    public function cell(string $name, Closure $callback): static {
        $this->cells[$name] = $callback;

        return $this;
    }

    /**
     * @return array{title: string, columns: list<array<string, mixed>>, rows: list<array<string, string>>}
     */
    public function export(mixed $input): array {
        $values = is_array($input) ? $input : [];
        $declared = $this->columns;

        $this->swap([]);
        $this->columns($this->filterColumns);
        $this->pool($this->optionals);

        $pooled = $this->columns;
        $filterable = array_column($pooled, 'name');
        $outputs = $this->visible($this->outputs($declared, $pooled, array_get_value($values, 'columns')));
        $fields = $this->fields($outputs, $this->locales);
        $filters = $this->requested(array_get_value($values, 'filters'), [...$filterable, ScheduleFilter::NAME]);
        $sorts = array_values(array_filter(Arr::wrap(array_get_value($values, 'sort')), fn (mixed $sort): bool => is_array($sort) && in_array(array_get_value($sort, 'name'), $filterable, true)));
        $used = [...array_keys($filters), ...array_column($sorts, 'name'), ...array_map(fn (string $sort): string => ltrim($sort, '-'), $this->sorting)];
        $names = array_column($outputs, 'name');

        $this->swap([...$outputs, ...array_values(array_filter($pooled, fn (Column $column): bool => in_array($column->name, $used, true) && !in_array($column->name, $names, true)))]);
        $this->attach($this->model);

        $items = Arr::wrap(array_get_value($values, 'id'));
        $query = $this->projection();

        $this->filter($query, $filters);

        if ($items !== []) {
            $query->whereIn("{$this->model->getTable()}.id", $items);
        }

        (new Sorting($this->sorting))->apply($query, $this->plan(), $sorts);

        $query->orderBy("{$this->model->getTable()}.id");

        foreach ($outputs as $column) {
            if ($column->options !== null) {
                $this->options[$column->name] = $this->flatten($column->options->options($this->model));
            }
        }

        $rows = [];

        foreach ($query->cursor() as $row) {
            $this->inspect($row);

            $data = [];

            foreach ($fields as $field) {
                $column = $field['column'];
                $raw = $row->getAttribute($field['name']);
                $override = array_get_value($this->cells, $column->name);

                $data[$field['name']] = $override instanceof Closure ? $this->text($override($raw, $row)) : $this->format($raw, $column);
            }

            $rows[] = $data;
        }

        return [
            'title' => $this->heading(),
            'columns' => array_map(fn (array $field): array => [...$this->shape($field['column']), 'name' => $field['name'], 'title' => $field['title']], $fields),
            'rows' => $rows
        ];
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    public function filterColumns(array $columns): static {
        $this->filterColumns = $columns;

        return $this;
    }

    /**
     * @param list<string>|null $locales
     */
    public function locales(?array $locales): static {
        $this->locales = $locales;

        return $this;
    }

    /**
     * @param list<string|array<string, mixed>> $optionals
     */
    public function optionals(array $optionals): static {
        $this->optionals = $optionals;

        return $this;
    }

    public function selectable(bool $selectable): static {
        $this->selectable = $selectable;

        return $this;
    }

    /**
     * @param list<string> $sorting
     */
    public function sorting(array $sorting): static {
        $this->sorting = $sorting;

        return $this;
    }

    /**
     * @param list<Option> $options
     * @param array<string, string> $carry
     * @return array<string, string>
     */
    private function flatten(array $options, array $carry = []): array {
        foreach ($options as $option) {
            if ($option->selectable) {
                $carry[strval($option->id)] = $option->title;
            }

            $carry = $this->flatten($option->children, $carry);
        }

        return $carry;
    }

    private function format(mixed $value, Column $column): string {
        if ($value === null) {
            return '';
        }

        if ($column->presentation === Presentation::MultiSelect) {
            return implode(', ', array_map(fn (mixed $item): string => $this->label($item, $column->name), $this->many($value)));
        }

        if ($column->options !== null) {
            return $this->label($value, $column->name);
        }

        $format = $column->type->format();

        return $format === null ? $this->text($value) : $this->moment($value, $format);
    }

    private function label(mixed $value, string $name): string {
        $key = $this->text($value);
        $map = array_get_value($this->options, $name);
        $title = is_array($map) ? array_get_value($map, $key) : null;

        return is_string($title) ? $title : $key;
    }

    /**
     * @return list<mixed>
     */
    private function many(mixed $value): array {
        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? array_values($decoded) : [$value];
    }

    private function moment(mixed $value, string $format): string {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        return is_string($value) ? Carbon::parse($value)->format($format) : '';
    }

    /**
     * @param list<Column> $declared
     * @param list<Column> $pooled
     * @return list<Column>
     */
    private function outputs(array $declared, array $pooled, mixed $requested): array {
        if (!$this->selectable) {
            return $declared;
        }

        $keyed = array_column($pooled, null, 'name');
        $names = is_array($requested) ? array_filter(array_unique(array_filter($requested, 'is_string')), fn (string $name): bool => array_key_exists($name, $keyed)) : [];

        if ($names === []) {
            $names = array_intersect(array_keys($keyed), array_column($declared, 'name'));
        }

        return array_values(array_map(fn (string $name): Column => $keyed[$name], $names));
    }

    /**
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function requested(mixed $filters, array $allowed): array {
        return is_array($filters) ? array_intersect_key($filters, array_flip($allowed)) : [];
    }

    private function text(mixed $value): string {
        if ($value instanceof DateTimeInterface) {
            return $this->moment($value, config('matrix.datetime-format'));
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? strval($value) : '';
    }

    /**
     * @param list<Column> $columns
     * @return list<Column>
     */
    private function visible(array $columns): array {
        $hidden = $this->model->getHidden();

        return array_values(array_filter($columns, fn (Column $column): bool => $column->presentation !== Presentation::Hidden && $column->presentation !== Presentation::Password && !in_array($column->name, $hidden, true)));
    }

}
