<?php //>

namespace MatrixPlatform\Support\Scaffold;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Subject;
use RuntimeException;

class ScaffoldPlan {

    public static function build(
        SchemaIntrospector $introspector,
        PresentationGuesser $guesser,
        MetadataRegistry $registry,
        PackageRegistry $packages,
        Subject $subject,
        string $table,
        ?string $model,
        ?string $title,
        ?string $parent,
        ?string $alias,
        string $namespace
    ): self {
        $columns = $introspector->columns($table);
        $modelName = $model === null || $model === '' ? self::defaultModelName($table) : $model;
        $titleField = self::resolveTitleField($columns, $title);
        $resolvedAlias = $alias === null || $alias === '' ? Str::kebab($modelName) : $alias;
        $parentInfo = $parent === null ? null : self::resolveParent($packages, $registry, $columns, $parent);
        $path = self::resolvePath($subject, $resolvedAlias, $parentInfo);
        $sortable = array_key_exists('ranking', $columns);
        $arrangeable = array_key_exists('enable_time', $columns) && array_key_exists('disable_time', $columns);
        $groups = self::groupColumns($columns, $titleField);
        $customFields = [];

        foreach ($groups as $group) {
            if ($group['kind'] === 'custom') {
                array_push($customFields, ...$group['names']);
            }
        }

        $presentations = [];

        foreach ($customFields as $name) {
            $presentations[$name] = $guesser->guess($name, $columns[$name]->type);
        }

        $notes = self::pendingNotes($introspector, $table, $columns, $customFields, $presentations, $titleField);

        return new self(
            $table,
            $modelName,
            "{$modelName}Declaration",
            $namespace,
            $resolvedAlias,
            $path,
            $titleField,
            $sortable,
            $arrangeable,
            $parentInfo,
            $columns,
            $groups,
            $customFields,
            $presentations,
            $notes
        );
    }

    /**
     * @param list<array{kind: 'primary'|'title'|'ranking'|'schedules'|'auditings'|'custom', names: non-empty-list<string>}> $groups
     * @param list<string> $buffer
     * @return list<array{kind: 'primary'|'title'|'ranking'|'schedules'|'auditings'|'custom', names: non-empty-list<string>}>
     */
    private static function closeBuffer(array $groups, array $buffer): array {
        if ($buffer !== []) {
            $groups[] = ['kind' => 'custom', 'names' => $buffer];
        }

        return $groups;
    }

    private static function defaultModelName(string $table): string {
        $parts = explode('_', $table, 2);
        $base = count($parts) === 2 ? $parts[1] : $parts[0];

        return Str::studly($base);
    }

    /**
     * @param array<string, ResolvedColumn> $columns
     * @return list<array{kind: 'primary'|'title'|'ranking'|'schedules'|'auditings'|'custom', names: non-empty-list<string>}>
     */
    private static function groupColumns(array $columns, string $titleField): array {
        $names = array_keys($columns);
        $total = count($names);
        $primaryKey = array_key_first(Definitions::primaryKey());
        $rankingKey = array_key_first(Definitions::ranking());
        [$enableKey, $disableKey] = array_keys(Definitions::schedules());
        [$creatorKey, $createKey, $updaterKey, $updateKey] = array_keys(Definitions::auditings());
        $groups = [];
        $buffer = [];
        $index = 0;

        while ($index < $total) {
            $name = $names[$index];

            if ($name === $primaryKey) {
                $groups = self::closeBuffer($groups, $buffer);
                $buffer = [];
                $groups[] = ['kind' => 'primary', 'names' => [$name]];
                $index++;

                continue;
            }

            if ($name === 'title' && $name === $titleField) {
                $groups = self::closeBuffer($groups, $buffer);
                $buffer = [];
                $groups[] = ['kind' => 'title', 'names' => [$name]];
                $index++;

                continue;
            }

            if ($name === $rankingKey) {
                $groups = self::closeBuffer($groups, $buffer);
                $buffer = [];
                $groups[] = ['kind' => 'ranking', 'names' => [$name]];
                $index++;

                continue;
            }

            if ($name === $enableKey && array_key_exists($index + 1, $names) && $names[$index + 1] === $disableKey) {
                $groups = self::closeBuffer($groups, $buffer);
                $buffer = [];
                $groups[] = ['kind' => 'schedules', 'names' => [$name, $names[$index + 1]]];
                $index += 2;

                continue;
            }

            if ($name === $creatorKey && array_key_exists($index + 1, $names) && $names[$index + 1] === $createKey) {
                $hasUpdater = array_key_exists($index + 3, $names) && $names[$index + 2] === $updaterKey && $names[$index + 3] === $updateKey;
                $members = $hasUpdater ? [$name, $names[$index + 1], $names[$index + 2], $names[$index + 3]] : [$name, $names[$index + 1]];
                $groups = self::closeBuffer($groups, $buffer);
                $buffer = [];
                $groups[] = ['kind' => 'auditings', 'names' => $members];
                $index += count($members);

                continue;
            }

            $buffer[] = $name;
            $index++;
        }

        return self::closeBuffer($groups, $buffer);
    }

    /**
     * @param array<string, ResolvedColumn> $columns
     * @param list<string> $customFields
     * @param array<string, array{presentation: Presentation|string|null, sensitive: bool}> $presentations
     * @return list<string>
     */
    private static function pendingNotes(SchemaIntrospector $introspector, string $table, array $columns, array $customFields, array $presentations, string $titleField): array {
        $notes = [];

        foreach ($customFields as $name) {
            if ($name === $titleField && $name === 'title') {
                continue;
            }

            $result = $presentations[$name];

            if ($result['sensitive']) {
                $notes[] = "HIGH RISK: '{$name}' looks like a sensitive field by name and was defaulted to Presentation::Hidden; verify manually if it should actually be shown.";

                continue;
            }

            if ($result['presentation'] === null) {
                $notes[] = "Could not guess a presentation for '{$name}'; please review the generated Declaration.";
            }
        }

        foreach ($columns as $name => $column) {
            if ($column->translatable && $column->missingLocales !== []) {
                $notes[] = "Translatable field '{$name}' is missing column(s) for locale(s): " . implode(', ', $column->missingLocales) . '.';
            }
        }

        foreach ($introspector->compositeUniqueConstraints($table) as $constraint) {
            $notes[] = "Composite unique constraint '{$constraint}' was detected but not applied automatically; please verify manually.";
        }

        return $notes;
    }

    private static function quote(string $value): string {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    /**
     * @param array<string, ResolvedColumn> $columns
     * @return array{relation: string, column: string, modelClass: class-string<Model>, alias: string}
     */
    private static function resolveParent(PackageRegistry $packages, MetadataRegistry $registry, array $columns, string $parent): array {
        $column = "{$parent}_id";

        if (!array_key_exists($column, $columns) || $columns[$column]->foreignTable === null) {
            throw new RuntimeException("parent-foreign-key-not-found: no single-column foreign key constraint found on column '{$column}'");
        }

        $referencedTable = $columns[$column]->foreignTable;

        foreach ($packages->models() as $model) {
            if (!is_a($model, Model::class, true)) {
                continue;
            }

            $metadata = $registry->of($model);

            if ($metadata === null || (new $model())->getTable() !== $referencedTable) {
                continue;
            }

            return ['relation' => $parent, 'column' => $column, 'modelClass' => $model, 'alias' => $metadata->alias];
        }

        throw new RuntimeException("parent-model-not-found: no declared (#[Declared]) model found for table '{$referencedTable}'");
    }

    /**
     * @param array{relation: string, column: string, modelClass: class-string<Model>, alias: string}|null $parent
     */
    private static function resolvePath(Subject $subject, string $alias, ?array $parent): string {
        if ($parent === null) {
            return $alias;
        }

        $class = $parent['modelClass'];
        $prefix = $subject->prefix(new $class());

        return Subject::joinSegment($prefix, $parent['column'], $alias);
    }

    /**
     * @param array<string, ResolvedColumn> $columns
     */
    private static function resolveTitleField(array $columns, ?string $given): string {
        if ($given !== null && $given !== '') {
            if (!array_key_exists($given, $columns)) {
                throw new RuntimeException("title-column-not-found: '{$given}' is not a column on this table");
            }

            return $given;
        }

        if (array_key_exists('title', $columns)) {
            return 'title';
        }

        if (array_key_exists('name', $columns)) {
            return 'name';
        }

        foreach ($columns as $name => $column) {
            if ($column->type === ColumnType::Text && !$column->nullable) {
                return $name;
            }
        }

        throw new RuntimeException('title-not-guessable: could not guess a title column from the schema, pass --title explicitly');
    }

    /**
     * @return array<string, string>
     */
    public function controllerTokens(): array {
        $imports = ["{$this->namespace}\\Models\\{$this->modelName}", 'MatrixPlatform\\Http\\Controllers\\Admin\\CrudController'];

        return [
            'namespace' => "{$this->namespace}\\Http\\Controllers\\Admin",
            'uses' => $this->useBlock($imports),
            'controller' => "{$this->modelName}Controller",
            'model' => $this->modelName
        ];
    }

    /**
     * @return array<string, string>
     */
    public function declarationTokens(): array {
        $imports = [
            'MatrixPlatform\\Columns\\Declarations\\Declares',
            'MatrixPlatform\\Columns\\Declarations\\Definition',
            'MatrixPlatform\\Columns\\Declarations\\Definitions',
            'MatrixPlatform\\Support\\Metadata'
        ];

        if ($this->usesPresentationEnum()) {
            $imports[] = 'MatrixPlatform\\Columns\\Presentation';
        }

        return [
            'namespace' => "{$this->namespace}\\Models\\Declarations",
            'uses' => $this->useBlock($imports),
            'declaration' => $this->declarationName,
            'segments' => $this->segmentsBlock(),
            'metadata' => $this->metadataArguments()
        ];
    }

    /**
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    public function modelI18nTokens(array $labels): array {
        if ($this->customFields === []) {
            return ['entries' => ''];
        }

        $lines = array_map(fn (string $name): string => "    '{$name}' => " . self::quote(strval(array_get_value($labels, $name, "TODO: {$name}"))) . ',', $this->customFields);

        return ['entries' => "\n" . implode("\n\n", $lines) . "\n"];
    }

    /**
     * @return array<string, string>
     */
    public function modelTokens(): array {
        $imports = ["{$this->namespace}\\Models\\Declarations\\{$this->declarationName}", 'MatrixPlatform\\Attributes\\Declared', 'MatrixPlatform\\Models\\BaseModel'];

        if ($this->usesCarbon()) {
            $imports[] = 'Illuminate\\Support\\Carbon';
        }

        if ($this->parent !== null) {
            $imports[] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
            $imports[] = $this->parent['modelClass'];
        }

        return [
            'namespace' => "{$this->namespace}\\Models",
            'uses' => $this->useBlock($imports),
            'properties' => $this->propertiesBlock(),
            'declaration' => $this->declarationName,
            'model' => $this->modelName,
            'table' => $this->table,
            'members' => $this->membersBlock()
        ];
    }

    /**
     * @param array{relation: string, column: string, modelClass: class-string<Model>, alias: string}|null $parent
     * @param array<string, ResolvedColumn> $columns
     * @param list<array{kind: 'primary'|'title'|'ranking'|'schedules'|'auditings'|'custom', names: non-empty-list<string>}> $groups
     * @param list<string> $customFields
     * @param array<string, array{presentation: Presentation|string|null, sensitive: bool}> $presentations
     * @param list<string> $pendingNotes
     */
    private function __construct(
        public readonly string $table,
        public readonly string $modelName,
        public readonly string $declarationName,
        public readonly string $namespace,
        public readonly string $alias,
        public readonly string $path,
        public readonly string $titleField,
        public readonly bool $sortable,
        public readonly bool $arrangeable,
        public readonly ?array $parent,
        public readonly array $columns,
        private readonly array $groups,
        public readonly array $customFields,
        public readonly array $presentations,
        public readonly array $pendingNotes
    ) {}

    /**
     * @return array<string, string>
     */
    private function castsMap(): array {
        $casts = [];

        if ($this->arrangeable) {
            $casts['enable_time'] = 'datetime';
            $casts['disable_time'] = 'datetime';
        }

        foreach ($this->customFields as $name) {
            $cast = match ($this->columns[$name]->type) {
                ColumnType::Date => 'date',
                ColumnType::DateTime => 'datetime',
                ColumnType::Json => 'array',
                default => null
            };

            if ($cast !== null) {
                $casts[$name] = $cast;
            }
        }

        ksort($casts);

        return $casts;
    }

    private function castsMember(): ?string {
        $casts = $this->castsMap();

        if ($casts === []) {
            return null;
        }

        $lines = [];

        foreach ($casts as $field => $cast) {
            $lines[] = "            '{$field}' => '{$cast}'";
        }

        return "    /**\n     * @return array<string, string>\n     */\n    protected function casts(): array {\n        return [\n" . implode(",\n", $lines) . "\n        ];\n    }";
    }

    /**
     * @param non-empty-list<string> $names
     */
    private function customArrayBlock(array $names): string {
        $entries = [];

        foreach ($names as $name) {
            $entries[] = "                '{$name}' => " . $this->definitionCall($name);
        }

        return "            [\n" . implode(",\n", $entries) . "\n            ]";
    }

    private function definitionCall(string $name): string {
        $column = $this->columns[$name];
        $presentation = array_key_exists($name, $this->presentations) ? $this->presentations[$name]['presentation'] : null;
        $factory = match ($column->type) {
            ColumnType::Boolean => 'boolean',
            ColumnType::Date => 'date',
            ColumnType::DateTime => 'dateTime',
            ColumnType::Float => 'float',
            ColumnType::Integer => 'integer',
            ColumnType::Json => 'json',
            ColumnType::Text => 'text'
        };
        $args = [];

        if ($presentation instanceof Presentation) {
            $args[] = "Presentation::{$presentation->name}";
        } elseif (is_string($presentation)) {
            $args[] = "'{$presentation}'";
        }

        if ($column->translatable) {
            $args[] = 'translatable: true';
        }

        if ($column->unique) {
            $args[] = 'unique: true';
        }

        return "Definition::{$factory}(" . implode(', ', $args) . ')';
    }

    private function membersBlock(): string {
        $parts = array_values(array_filter([$this->relationMember(), $this->castsMember()], fn (?string $part): bool => $part !== null));

        return $parts === [] ? '' : "\n" . implode("\n\n", $parts) . "\n";
    }

    private function metadataArguments(): string {
        $needsPositionalTitle = $this->titleField !== 'title' || $this->parent !== null;
        $args = ["'{$this->alias}'"];

        if ($needsPositionalTitle) {
            $args[] = "'{$this->titleField}'";
        }

        if ($this->parent !== null) {
            $args[] = "'{$this->parent['relation']}'";
        }

        if ($this->arrangeable) {
            $args[] = "enable: 'enable_time'";
            $args[] = "disable: 'disable_time'";
        }

        if ($this->sortable) {
            $args[] = "ranking: 'ranking'";
        }

        return implode(', ', $args);
    }

    private function propertiesBlock(): string {
        $lines = [];

        foreach ($this->columns as $name => $column) {
            if ($column->translatable) {
                $present = array_values(array_diff(locales(), $column->missingLocales));

                foreach ($present as $locale) {
                    $lines[] = " * @property ?string \${$name}__{$locale}";
                }

                continue;
            }

            $prefix = $column->nullable ? '?' : '';
            $lines[] = " * @property {$prefix}{$this->propertyType($column)} \${$name}";
        }

        return implode("\n", $lines);
    }

    private function propertyType(ResolvedColumn $column): string {
        return match ($column->type) {
            ColumnType::Boolean => 'bool',
            ColumnType::Date, ColumnType::DateTime => 'Carbon',
            ColumnType::Float => 'float',
            ColumnType::Integer => 'int',
            ColumnType::Json => 'array<int, array<string, mixed>>',
            ColumnType::Text => 'string'
        };
    }

    private function relationMember(): ?string {
        if ($this->parent === null) {
            return null;
        }

        $parentShortName = class_basename($this->parent['modelClass']);

        return "    /**\n     * @return BelongsTo<{$parentShortName}, \$this>\n     */\n    public function {$this->parent['relation']}(): BelongsTo {\n        return \$this->belongsTo({$parentShortName}::class, '{$this->parent['column']}');\n    }";
    }

    private function segmentsBlock(): string {
        $lines = [];

        foreach ($this->groups as $group) {
            $lines[] = match ($group['kind']) {
                'primary' => '            Definitions::primaryKey()',
                'title' => '            Definitions::title(' . ($this->columns[$this->titleField]->unique ? 'true' : '') . ')',
                'ranking' => '            Definitions::ranking()',
                'schedules' => '            Definitions::schedules()',
                'auditings' => count($group['names']) === 4 ? '            Definitions::auditings()' : '            Definitions::auditings(false)',
                'custom' => $this->customArrayBlock($group['names'])
            };
        }

        return implode(",\n", $lines);
    }

    /**
     * @param list<string> $imports
     */
    private function useBlock(array $imports): string {
        $unique = array_values(array_unique($imports));

        usort($unique, strcmp(...));

        return implode("\n", array_map(fn (string $import): string => "use {$import};", $unique));
    }

    private function usesCarbon(): bool {
        foreach ($this->columns as $column) {
            if ($column->type === ColumnType::Date || $column->type === ColumnType::DateTime) {
                return true;
            }
        }

        return false;
    }

    private function usesPresentationEnum(): bool {
        foreach ($this->presentations as $result) {
            if ($result['presentation'] instanceof Presentation) {
                return true;
            }
        }

        return false;
    }

}
