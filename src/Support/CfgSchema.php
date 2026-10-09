<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Support\Facades\Validator;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Services\FileService;

/**
 * @phpstan-type Column array{name: string, title: string, type: string, presentation: string, readonly: bool, secret: bool, rule: list<string>, default: mixed, placeholder: string}
 */
class CfgSchema {

    public const DRIVES = ['drive-file', 'drive-image'];

    public const MASK = '••••••••';

    public function __construct(private Resources $resources) {}

    /**
     * @param array<string, mixed> $override
     * @param list<Column> $columns
     * @param array<string, mixed> $values
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public function apply(array $override, array $columns, array $values, array $defaults): array {
        $writable = $this->writable($columns, $values);

        $this->validate(array_filter($writable, fn (string $key): bool => !$this->cleared($values[$key]), ARRAY_FILTER_USE_KEY), $values);

        foreach ($writable as $key => $column) {
            $coerced = $this->cleared($values[$key]) ? null : $this->coerce($column, $values[$key]);

            if ($coerced === null || $coerced === array_get_value($defaults, $key)) {
                unset($override[$key]);
            } else {
                $override[$key] = $coerced;
            }
        }

        return $override;
    }

    public function cleared(mixed $value): bool {
        return $value === null || $value === '';
    }

    /**
     * @param array<string, mixed> $defaults
     * @return list<Column>
     */
    public function columns(ResourceGroup $group, string $id, array $defaults): array {
        $schema = $this->resources->getStyleBundle($id) + $this->resources->getStyleBundle($group->value);
        $columns = [];

        foreach ($defaults as $key => $value) {
            $meta = array_get_value($schema, $key);
            $meta = is_array($meta) ? $meta : [];
            $type = $this->type($meta);

            if (is_array($value) && $type !== ColumnType::Json) {
                continue;
            }

            $rule = array_get_value($meta, 'rule');
            $presentation = array_get_value($meta, 'presentation');

            $secret = array_get_value($meta, 'secret') === true;

            $columns[] = [
                'name' => strval($key),
                'title' => $this->title("{$id}.{$key}", $this->title("{$group->value}.{$key}", strval($key))),
                'type' => $type->value,
                'presentation' => is_string($presentation) ? $presentation : 'plain',
                'readonly' => array_get_value($meta, 'readonly') === true,
                'secret' => $secret,
                'rule' => is_array($rule) ? array_map(strval(...), array_values($rule)) : [$type->rule()],
                'default' => $secret ? '' : $value,
                'placeholder' => $secret || !is_scalar($value) ? '' : strval($value)
            ];
        }

        return $columns;
    }

    public function title(string $token, string $fallback): string {
        $label = i18n("resource.{$token}");

        return $label === "resource.{$token}" ? $fallback : $label;
    }

    /**
     * @param array<string, mixed> $column
     */
    private function coerce(array $column, mixed $value): mixed {
        if (in_array(array_get_value($column, 'presentation'), self::DRIVES, true) && is_array($value)) {
            return app(FileService::class)->resolveDriveReferences(array_values(array_filter($value, is_array(...))), actor()->requireUser());
        }

        return match (array_get_value($column, 'type')) {
            ColumnType::Boolean->value => filter_var($value, FILTER_VALIDATE_BOOL),
            ColumnType::Float->value => floatval($value),
            ColumnType::Integer->value => intval($value),
            default => is_scalar($value) ? strval($value) : $value
        };
    }

    /**
     * @param array<string, mixed> $column
     */
    private function masked(array $column, mixed $value): bool {
        return array_get_value($column, 'secret') === true && $value === self::MASK;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function type(array $meta): ColumnType {
        $declared = array_get_value($meta, 'type');
        $type = is_string($declared) ? ColumnType::tryFrom($declared) : null;

        return $type === null ? ColumnType::Text : $type;
    }

    /**
     * @param array<string, Column> $writable
     * @param array<string, mixed> $values
     */
    private function validate(array $writable, array $values): void {
        $rules = [];

        foreach ($writable as $key => $column) {
            $rules[str_replace('.', '\\.', $key)] = $column['rule'];
        }

        Validator::make(array_intersect_key($values, $writable), $rules)->validate();
    }

    /**
     * @param list<Column> $columns
     * @param array<string, mixed> $values
     * @return array<string, Column>
     */
    private function writable(array $columns, array $values): array {
        $writable = [];

        foreach ($columns as $column) {
            $key = $column['name'];

            if (!$column['readonly'] && array_key_exists($key, $values) && !$this->masked($column, $values[$key])) {
                $writable[$key] = $column;
            }
        }

        return $writable;
    }

}
