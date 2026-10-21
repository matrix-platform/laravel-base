<?php //>

namespace MatrixPlatform\Support\Scaffold;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Columns\ColumnType;

class SchemaIntrospector {

    /**
     * @var array<string, list<array{name: string, columns: list<string>, partial: bool, softDelete: bool, expression: bool}>>
     */
    private array $uniqueIndexesCache = [];

    /**
     * @return array<string, ResolvedColumn>
     */
    public function columns(string $table): array {
        $raw = $this->rawColumns($table);
        $groups = $this->translatableGroups($raw);
        $unique = [];

        foreach ($this->uniqueIndexes($table) as $index) {
            if (count($index['columns']) === 1 && !$index['expression'] && (!$index['partial'] || $index['softDelete'])) {
                $unique[] = $index['columns'][0];
            }
        }

        $foreign = $this->foreignKeys($table);
        $fields = [];

        foreach ($groups as $field => $present) {
            foreach ($present as $locale) {
                $fields["{$field}__{$locale}"] = $field;
            }
        }

        $resolved = [];
        $emitted = [];

        foreach (array_keys($raw) as $name) {
            if (array_key_exists($name, $fields)) {
                $field = $fields[$name];

                if (array_key_exists($field, $emitted)) {
                    continue;
                }

                $emitted[$field] = true;
                $present = $groups[$field];
                $column = "{$field}__{$present[0]}";
                $info = $raw[$column];
                $missing = array_values(array_diff(locales(), $present));

                $resolved[$field] = new ResolvedColumn($field, $info['type'], $info['nullable'], true, in_array($column, $unique, true), null, $info['comment'], $missing, $info['range']);

                continue;
            }

            $info = $raw[$name];

            $resolved[$name] = new ResolvedColumn($name, $info['type'], $info['nullable'], false, in_array($name, $unique, true), array_key_exists($name, $foreign) ? $foreign[$name] : null, $info['comment'], [], $info['range']);
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public function compositeUniqueConstraints(string $table): array {
        $names = [];

        foreach ($this->uniqueIndexes($table) as $index) {
            if (count($index['columns']) > 1 && !$index['expression'] && (!$index['partial'] || $index['softDelete'])) {
                $names[] = $index['name'];
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public function partialUniqueIndexes(string $table): array {
        $names = [];

        foreach ($this->uniqueIndexes($table) as $index) {
            if ($index['expression'] || ($index['partial'] && !$index['softDelete'])) {
                $names[] = $index['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public function softDeleteUniqueColumns(string $table): array {
        $columns = [];

        foreach ($this->uniqueIndexes($table) as $index) {
            if (count($index['columns']) === 1 && !$index['expression'] && $index['softDelete']) {
                $columns[$index['columns'][0]] = $index['name'];
            }
        }

        return $columns;
    }

    public function tableExists(string $table): bool {
        return DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->exists();
    }

    private function columnType(string $dataType): ColumnType {
        return match (true) {
            $dataType === 'boolean' => ColumnType::Boolean,
            $dataType === 'date' => ColumnType::Date,
            in_array($dataType, ['timestamp without time zone', 'timestamp with time zone'], true) => ColumnType::DateTime,
            in_array($dataType, ['numeric', 'double precision', 'real'], true) => ColumnType::Float,
            in_array($dataType, ['smallint', 'integer', 'bigint'], true) => ColumnType::Integer,
            in_array($dataType, ['json', 'jsonb'], true) => ColumnType::Json,
            default => ColumnType::Text
        };
    }

    /**
     * @return array<string, string>
     */
    private function foreignKeys(string $table): array {
        $rows = DB::table('pg_constraint as c')
            ->join('pg_class as t', 't.oid', '=', 'c.conrelid')
            ->join('pg_namespace as n', 'n.oid', '=', 't.relnamespace')
            ->join('pg_class as r', 'r.oid', '=', 'c.confrelid')
            ->join('pg_attribute as a', function (JoinClause $join): void {
                $join->on('a.attrelid', '=', 'c.conrelid')->whereRaw('a.attnum = c.conkey[1]');
            })
            ->where('c.contype', 'f')
            ->where('n.nspname', 'public')
            ->where('t.relname', $table)
            ->whereRaw('cardinality(c.conkey) = 1')
            ->get(['a.attname as column_name', 'r.relname as referenced_table']);

        $foreign = [];

        foreach ($rows as $row) {
            $foreign[strval($row->column_name)] = strval($row->referenced_table);
        }

        return $foreign;
    }

    private function range(string $dataType, mixed $precision, mixed $scale): ?string {
        if ($dataType === 'numeric') {
            if (!is_numeric($precision)) {
                return null;
            }

            $fraction = is_numeric($scale) ? intval($scale) : 0;
            $whole = intval($precision) - $fraction;
            $max = ($whole > 0 ? str_repeat('9', $whole) : '0') . ($fraction > 0 ? '.' . str_repeat('9', $fraction) : '');

            return "-{$max},{$max}";
        }

        return match ($dataType) {
            'bigint' => '-9223372036854775808,9223372036854775807',
            'real' => '-3.4028235e38,3.4028235e38',
            'smallint' => '-32768,32767',
            default => null
        };
    }

    /**
     * @return array<string, array{type: ColumnType, nullable: bool, comment: ?string, range: ?string}>
     */
    private function rawColumns(string $table): array {
        $rows = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->orderBy('ordinal_position')
            ->selectRaw("column_name, data_type, numeric_precision, numeric_scale, is_nullable, col_description((table_schema || '.' || table_name)::regclass, ordinal_position) as comment")
            ->get();

        $columns = [];

        foreach ($rows as $row) {
            $columns[strval($row->column_name)] = [
                'type' => $this->columnType(strval($row->data_type)),
                'nullable' => strval($row->is_nullable) === 'YES',
                'comment' => is_string($row->comment) && $row->comment !== '' ? $row->comment : null,
                'range' => $this->range(strval($row->data_type), $row->numeric_precision, $row->numeric_scale)
            ];
        }

        return $columns;
    }

    /**
     * @param array<string, array{type: ColumnType, nullable: bool, comment: ?string, range: ?string}> $raw
     * @return array<string, list<string>>
     */
    private function translatableGroups(array $raw): array {
        $groups = [];

        foreach (locales() as $locale) {
            $suffix = "__{$locale}";

            foreach (array_keys($raw) as $name) {
                if (!str_ends_with($name, $suffix)) {
                    continue;
                }

                $field = substr($name, 0, -strlen($suffix));

                if ($field !== '' && !array_key_exists($field, $raw)) {
                    $groups[$field][] = $locale;
                }
            }
        }

        return $groups;
    }

    /**
     * @return list<array{name: string, columns: list<string>, partial: bool, softDelete: bool, expression: bool}>
     */
    private function uniqueIndexes(string $table): array {
        if (array_key_exists($table, $this->uniqueIndexesCache)) {
            return $this->uniqueIndexesCache[$table];
        }

        $rows = DB::table('pg_index as i')
            ->join('pg_class as c', 'c.oid', '=', 'i.indexrelid')
            ->join('pg_class as t', 't.oid', '=', 'i.indrelid')
            ->join('pg_namespace as n', 'n.oid', '=', 't.relnamespace')
            ->where('n.nspname', 'public')
            ->where('t.relname', $table)
            ->where('i.indisunique', true)
            ->where('i.indisprimary', false)
            ->orderBy('c.relname')
            ->selectRaw("c.relname as name, pg_get_expr(i.indpred, i.indrelid) as predicate, i.indexprs is not null as expression, array_to_string(array(select a.attname from unnest(i.indkey) with ordinality as k(attnum, position) join pg_attribute as a on a.attrelid = i.indrelid and a.attnum = k.attnum order by k.position), ',') as columns")
            ->get();

        $indexes = [];

        foreach ($rows as $row) {
            $predicate = is_string($row->predicate) ? $row->predicate : null;
            $columns = strval($row->columns);

            $indexes[] = [
                'name' => strval($row->name),
                'columns' => $columns === '' ? [] : explode(',', $columns),
                'partial' => $predicate !== null,
                'softDelete' => $predicate !== null && strtolower(strval(preg_replace('/[\s()]/', '', $predicate))) === 'deleted_atisnull',
                'expression' => (bool) $row->expression
            ];
        }

        return $this->uniqueIndexesCache[$table] = $indexes;
    }

}
