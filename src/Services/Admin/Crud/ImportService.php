<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\Filtering;
use MatrixPlatform\Exceptions\ServiceException;

class ImportService extends CrudService {

    private const EXCLUDED = [Presentation::Composite, Presentation::DriveFile, Presentation::DriveImage, Presentation::Hidden, Presentation::MultiSelect, Presentation::Password];

    /**
     * @var array<string, Closure>
     */
    private array $lookups = [];

    /**
     * @var array<string, array<string, list<int|string>>>
     */
    private array $options = [];

    /**
     * @return array{count: int}
     */
    public function import(mixed $input): array {
        $rows = $this->rows($input);

        $this->narrow();

        $fields = $this->fields($this->columns, locales());
        $errors = [];
        $count = 0;

        foreach ($rows as $item) {
            [$data, $failures] = $this->convert($fields, $item['values'], $item['row']);

            if ($failures === []) {
                $failures = $this->insert($data);
            }

            if ($failures === []) {
                $count++;
            } else {
                $errors[] = ['row' => $item['row'], 'fields' => $failures];
            }
        }

        if ($errors !== []) {
            usort($errors, fn (array $a, array $b): int => $a['row'] <=> $b['row']);

            error('import-failed', 422, ['rows' => $errors]);
        }

        return ['count' => $count];
    }

    public function lookup(string $name, Closure $callback): static {
        $this->lookups[$name] = $callback;

        return $this;
    }

    /**
     * @return array{title: string, columns: list<array<string, mixed>>, rows: array{}}
     */
    public function template(): array {
        $this->narrow();

        $columns = [];

        foreach ($this->fields($this->columns, locales()) as $field) {
            $column = $field['column'];
            $map = array_get_value($this->options, $column->name);

            $columns[] = [
                ...$this->shape($column),
                'name' => $field['name'],
                'title' => $field['title'],
                'required' => $column->required,
                'options' => is_array($map) ? array_map(strval(...), array_keys($map)) : null
            ];
        }

        return ['title' => $this->heading(), 'columns' => $columns, 'rows' => []];
    }

    /**
     * @param array<string, list<int|string>> $carry
     * @param list<Option> $options
     * @return array<string, list<int|string>>
     */
    private function collect(array $carry, array $options): array {
        foreach ($options as $option) {
            if ($option->selectable) {
                $carry[$option->title][] = $option->id;
            }

            $carry = $this->collect($carry, $option->children);
        }

        return $carry;
    }

    /**
     * @param list<array{name: string, column: Column, title: string}> $fields
     * @param array<mixed> $values
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function convert(array $fields, array $values, int $row): array {
        $data = [];
        $failures = [];

        foreach ($fields as $field) {
            $column = $field['column'];
            $raw = array_get_value($values, $field['name']);
            $text = is_scalar($raw) ? trim(strval($raw)) : '';

            if ($text === '') {
                $data[$field['name']] = $column->type === ColumnType::Boolean ? false : null;

                continue;
            }

            try {
                $data[$field['name']] = $this->value($column, $field['name'], $text, $row);
            } catch (ServiceException $exception) {
                $failures = array_merge_recursive($failures, $this->failures($exception));
            }
        }

        return [$data, $failures];
    }

    /**
     * @return array<string, list<string>>
     */
    private function failures(ServiceException $exception): array {
        if ($exception->getError() !== 'validation-failed') {
            throw $exception;
        }

        $fields = array_get_value($exception->getExtra(), 'fields');

        return is_array($fields) ? $fields : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    private function insert(array $data): array {
        try {
            DB::transaction(fn () => $this->store($this->model->newInstance(), $data));
        } catch (ValidationException $exception) {
            return validation_fields($exception);
        } catch (ServiceException $exception) {
            return $this->failures($exception);
        } catch (QueryException) {
            return ['*' => ['query-failed']];
        }

        return [];
    }

    private function narrow(): void {
        $local = $this->local();
        $foreign = $this->foreign();
        $scoped = array_filter($this->columns, fn (Column $column): bool => in_array($column, $local, true) && $this->writable($column) && $column->name !== $foreign);
        $unsupported = array_filter($scoped, fn (Column $column): bool => $column->required && !$this->supported($column));

        if ($unsupported !== []) {
            error('import-column-unsupported', 500, ['columns' => array_values(array_map(fn (Column $column): string => $column->name, $unsupported))]);
        }

        $this->swap(array_values(array_filter($scoped, $this->supported(...))));

        foreach ($this->columns as $column) {
            if ($column->options !== null && !array_key_exists($column->name, $this->lookups)) {
                $this->options[$column->name] = $this->collect([], $column->options->options($this->model));
            }
        }
    }

    /**
     * @return list<array{row: int, values: array<mixed>}>
     */
    private function rows(mixed $input): array {
        $rows = is_array($input) ? array_get_value($input, 'rows') : null;

        if ($rows === null || $rows === []) {
            invalid('rows', 'required');
        }

        if (!is_array($rows) || !array_is_list($rows)) {
            invalid('rows', 'array');
        }

        $parsed = [];

        foreach ($rows as $item) {
            $row = is_array($item) ? array_get_value($item, 'row') : null;
            $values = is_array($item) ? array_get_value($item, 'values') : null;

            if (!is_int($row) || !is_array($values)) {
                invalid('rows', 'array');
            }

            $parsed[] = ['row' => $row, 'values' => $values];
        }

        return $parsed;
    }

    private function supported(Column $column): bool {
        return $column->type !== ColumnType::Json && !in_array($column->presentation, self::EXCLUDED, true) && $column->expression->field !== $this->model->getKeyName();
    }

    private function value(Column $column, string $name, string $text, int $row): mixed {
        $lookup = array_get_value($this->lookups, $column->name);

        if ($lookup instanceof Closure) {
            return $lookup($text, $row);
        }

        $map = array_get_value($this->options, $column->name);

        if (is_array($map)) {
            $ids = array_get_value($map, $text);

            if (!is_array($ids) || count($ids) !== 1) {
                invalid($name, 'in');
            }

            return $column->type === ColumnType::Text ? strval($ids[0]) : $ids[0];
        }

        $format = $column->type->format();

        if ($format !== null && !Filtering::formatted($text, $format)) {
            invalid($name, 'date');
        }

        return $text;
    }

}
