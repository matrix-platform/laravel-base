<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Presets;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Syntax\Expression;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\Variants;

class Form {

    /**
     * @return array<string, mixed>
     */
    private static function bundle(string $group, ?string $type): array {
        $resources = app(Resources::class);
        $shared = $resources->getI18nBundle("model/{$group}");
        $specific = $type === null ? null : $resources->getI18nBundle("model/{$group}-{$type}");

        return array_replace($shared === null ? [] : $shared, $specific === null ? [] : $specific);
    }

    /**
     * @param list<Column> $columns
     */
    public static function expand(Variants $variants, array $columns, mixed $input, ?Model $model): self {
        $expanded = [];
        $composites = [];
        $presets = [];

        foreach ($columns as $column) {
            if ($column->variantGroup === null) {
                $expanded[] = $column;

                continue;
            }

            $type = $variants->type($column->variantGroup, $model, $input);
            $variant = $variants->of($column->variantGroup, $type);

            if ($variant === null) {
                continue;
            }

            if ($variant instanceof Presets) {
                $presets[$column->name] = $variant->defaults();
            }

            foreach (self::subfields($column, $column->variantGroup, $type, $variant->definitions()) as $field => $subfield) {
                $composites[$column->name][$field] = $subfield;
                $expanded[] = $subfield;
            }
        }

        return new self($expanded, $composites, $presets);
    }

    /**
     * @param array<string, mixed> $bundle
     */
    private static function label(array $bundle, string $token): ?string {
        $found = array_get_value($bundle, $token);

        return is_string($found) ? $found : null;
    }

    /**
     * @param array<string, Definition> $definitions
     * @return array<string, Column>
     */
    private static function subfields(Column $composite, string $group, ?string $type, array $definitions): array {
        $bundle = self::bundle($group, $type);
        $columns = [];

        foreach ($definitions as $field => $definition) {
            if ($definition->presentation === Presentation::Composite) {
                error('nested-composite-not-supported');
            }

            $name = "{$composite->name}__{$field}";
            $options = $definition->options === null ? null : (is_string($definition->options) ? app($definition->options) : $definition->options);
            $presentation = $definition->presentation;
            $title = self::label($bundle, $field);

            $columns[$field] = new Column(
                expression: new Expression(null, [], $name, null, []),
                group: $definition->group,
                hint: self::label($bundle, "{$field}:hint"),
                name: $name,
                locked: false,
                op: null,
                options: $options,
                path: null,
                placeholder: self::label($bundle, "{$field}:placeholder"),
                presentation: $presentation === null ? ($options === null ? Presentation::Plain : Presentation::Select) : $presentation,
                readonly: $composite->readonly,
                remark: self::label($bundle, "{$field}:remark"),
                required: $definition->required,
                rule: $definition->rule instanceof Closure ? ($definition->rule)() : $definition->rule,
                sortable: false,
                section: $definition->section === null ? $composite->section : $definition->section,
                title: $title === null ? "{{$field}}" : $title,
                translatable: $definition->translatable,
                type: $definition->type,
                unique: false,
                variantGroup: null,
                virtual: false,
                props: $definition->props
            );
        }

        return $columns;
    }

    /**
     * @var array<string, true>
     */
    private array $subnames = [];

    /**
     * @param list<Column> $columns
     * @param array<string, array<string, Column>> $composites
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(public readonly array $columns, public readonly array $composites = [], public readonly array $presets = []) {
        foreach ($composites as $subfields) {
            foreach ($subfields as $subfield) {
                $this->subnames[$subfield->name] = true;
            }
        }
    }

    public function composed(Column $column): bool {
        return array_key_exists($column->name, $this->subnames);
    }

}
