<?php //>

namespace MatrixPlatform\Support\Scaffold;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Columns\ColumnType;

class SchemaIntrospector {

    /**
     * @var array<string, array<string, list<string>>>
     */
    private array $constraintColumnsCache = [];

    /**
     * @return array<string, ResolvedColumn>
     */
    public function columns(string $table): array {
        $raw = $this->rawColumns($table);
        $groups = $this->translatableGroups($raw);
        $unique = $this->singleColumnConstraints($table, 'UNIQUE');
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
                $info = $raw["{$field}__{$present[0]}"];
                $missing = array_values(array_diff(locales(), $present));

                $resolved[$field] = new ResolvedColumn($field, $info['type'], $info['nullable'], true, in_array("{$field}__{$present[0]}", $unique, true), null, $info['comment'], $missing);

                continue;
            }

            $info = $raw[$name];

            $resolved[$name] = new ResolvedColumn($name, $info['type'], $info['nullable'], false, in_array($name, $unique, true), array_key_exists($name, $foreign) ? $foreign[$name] : null, $info['comment']);
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public function compositeUniqueConstraints(string $table): array {
        $names = [];

        foreach ($this->constraintColumns($table, 'UNIQUE') as $name => $columns) {
            if (count($columns) > 1) {
                $names[] = $name;
            }
        }

        return $names;
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
     * @return array<string, list<string>>
     */
    private function constraintColumns(string $table, string $type): array {
        $key = "{$table}:{$type}";

        if (array_key_exists($key, $this->constraintColumnsCache)) {
            return $this->constraintColumnsCache[$key];
        }

        $rows = DB::table('information_schema.table_constraints as tc')
            ->join('information_schema.key_column_usage as kcu', function (JoinClause $join): void {
                $join->on('tc.constraint_name', '=', 'kcu.constraint_name')->on('tc.table_schema', '=', 'kcu.table_schema');
            })
            ->where('tc.table_schema', 'public')
            ->where('tc.table_name', $table)
            ->where('tc.constraint_type', $type)
            ->get(['tc.constraint_name', 'kcu.column_name']);

        $groups = [];

        foreach ($rows as $row) {
            $groups[strval($row->constraint_name)][] = strval($row->column_name);
        }

        return $this->constraintColumnsCache[$key] = $groups;
    }

    /**
     * @return array<string, string>
     */
    private function foreignKeys(string $table): array {
        $rows = DB::table('information_schema.table_constraints as tc')
            ->join('information_schema.key_column_usage as kcu', function (JoinClause $join): void {
                $join->on('tc.constraint_name', '=', 'kcu.constraint_name')->on('tc.table_schema', '=', 'kcu.table_schema');
            })
            ->join('information_schema.constraint_column_usage as ccu', 'tc.constraint_name', '=', 'ccu.constraint_name')
            ->where('tc.table_schema', 'public')
            ->where('tc.table_name', $table)
            ->where('tc.constraint_type', 'FOREIGN KEY')
            ->get(['tc.constraint_name', 'kcu.column_name', 'ccu.table_name as referenced_table']);

        $groups = [];

        foreach ($rows as $row) {
            $groups[strval($row->constraint_name)][] = [strval($row->column_name), strval($row->referenced_table)];
        }

        $foreign = [];

        foreach ($groups as $group) {
            if (count($group) === 1) {
                [$column, $referenced] = $group[0];
                $foreign[$column] = $referenced;
            }
        }

        return $foreign;
    }

    /**
     * @return array<string, array{type: ColumnType, nullable: bool, comment: ?string}>
     */
    private function rawColumns(string $table): array {
        $rows = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->orderBy('ordinal_position')
            ->selectRaw("column_name, data_type, is_nullable, col_description((table_schema || '.' || table_name)::regclass, ordinal_position) as comment")
            ->get();

        $columns = [];

        foreach ($rows as $row) {
            $columns[strval($row->column_name)] = [
                'type' => $this->columnType(strval($row->data_type)),
                'nullable' => strval($row->is_nullable) === 'YES',
                'comment' => is_string($row->comment) && $row->comment !== '' ? $row->comment : null
            ];
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private function singleColumnConstraints(string $table, string $type): array {
        $columns = [];

        foreach ($this->constraintColumns($table, $type) as $group) {
            if (count($group) === 1) {
                $columns[] = $group[0];
            }
        }

        return $columns;
    }

    /**
     * @param array<string, array{type: ColumnType, nullable: bool, comment: ?string}> $raw
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

}
