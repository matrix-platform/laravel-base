<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\Filtering;
use MatrixPlatform\Columns\Query\Sorting;

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
     * @var array<string, array<string, string>>
     */
    private array $options = [];

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
        $outputs = $this->visible();

        $this->columns($this->filterColumns);
        $this->attach($this->model);

        $values = is_array($input) ? $input : [];
        $query = $this->projection();

        (new Filtering())->apply($query, $this->plan(), array_get_value($values, 'filters'));
        (new Sorting($this->sorting))->apply($query, $this->plan(), array_get_value($values, 'sort'));

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

            foreach ($outputs as $column) {
                $raw = $row->getAttribute($column->name);
                $override = array_get_value($this->cells, $column->name);

                $data[$column->name] = $override instanceof Closure ? $this->text($override($raw, $row)) : $this->format($raw, $column);
            }

            $rows[] = $data;
        }

        return [
            'title' => $this->heading(),
            'columns' => array_map(fn (Column $column): array => $this->shape($column), $outputs),
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
            $carry[strval($option->id)] = $option->title;
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

        return match ($column->type) {
            ColumnType::Date => $this->moment($value, cfg('system.date-format')),
            ColumnType::DateTime => $this->moment($value, cfg('system.datetime-format')),
            default => $this->text($value)
        };
    }

    private function heading(): string {
        $title = $this->title();

        return $title === null ? $this->subject->alias($this->model) : $title;
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

    private function text(mixed $value): string {
        if ($value instanceof DateTimeInterface) {
            return $this->moment($value, cfg('system.datetime-format'));
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? strval($value) : '';
    }

    /**
     * @return list<Column>
     */
    private function visible(): array {
        $hidden = $this->model->getHidden();

        return array_values(array_filter($this->columns, fn (Column $column): bool => $column->presentation !== Presentation::Hidden && $column->presentation !== Presentation::Password && !in_array($column->name, $hidden, true)));
    }

}
