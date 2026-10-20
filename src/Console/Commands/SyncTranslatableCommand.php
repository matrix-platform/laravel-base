<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;

class SyncTranslatableCommand extends Command {

    protected $description = 'Add the missing per-locale entity columns for every translatable field';

    protected $signature = 'matrix:sync-translatable';

    /**
     * @var array<string, array<string, string>>
     */
    private array $columns = [];

    public function handle(): int {
        $this->columns = [];
        $registry = app(MetadataRegistry::class);

        foreach ($registry->unresolvedModels(app(PackageRegistry::class)) as $name) {
            $this->warn("Skipping {$name}: class could not be autoloaded (models are discovered only directly under src/Models and app/Models, with their default namespaces)");
        }

        foreach ($registry->declaredModels(app(PackageRegistry::class)) as $model => $declares) {
            $table = (new $model())->getTable();

            foreach ($declares->definitions() as $field => $definition) {
                if ($definition->translatable) {
                    $this->sync($table, $field);
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function columns(string $table): array {
        if (!array_key_exists($table, $this->columns)) {
            $this->columns[$table] = DB::table('pg_attribute')
                ->selectRaw('attname as column_name, format_type(atttypid, atttypmod) as data_type')
                ->whereRaw('attrelid = to_regclass(quote_ident(?))', [$table])
                ->where('attnum', '>', 0)
                ->where('attisdropped', false)
                ->pluck('data_type', 'column_name')
                ->all();
        }

        return $this->columns[$table];
    }

    private function source(string $table, string $field): ?string {
        $columns = $this->columns($table);

        foreach (locales() as $locale) {
            if (array_key_exists("{$field}__{$locale}", $columns)) {
                return $locale;
            }
        }

        return null;
    }

    private function sync(string $table, string $field): void {
        $columns = $this->columns($table);
        $source = $this->source($table, $field);

        if ($source === null) {
            $this->warn("Skipping {$table}.{$field}: no existing locale column to copy the type from");

            return;
        }

        $type = $columns["{$field}__{$source}"];
        $unique = $this->unique($table, "{$field}__{$source}");
        $grammar = DB::getQueryGrammar();

        foreach (locales() as $locale) {
            $column = "{$field}__{$locale}";

            if (array_key_exists($column, $columns)) {
                continue;
            }

            DB::statement('ALTER TABLE ' . $grammar->wrapTable($table) . ' ADD COLUMN ' . $grammar->wrap($column) . " {$type}");

            $this->columns[$table][$column] = $type;

            $this->info("Added {$table}.{$column} ({$type})");

            if ($unique) {
                DB::statement('ALTER TABLE ' . $grammar->wrapTable($table) . ' ADD CONSTRAINT ' . $grammar->wrap("{$table}_{$column}_unique") . ' UNIQUE (' . $grammar->wrap($column) . ')');

                $this->info("Added unique constraint on {$table}.{$column}");
            }
        }
    }

    private function unique(string $table, string $column): bool {
        return DB::table('pg_index as i')
            ->join('pg_attribute as a', fn ($join) => $join->on('a.attrelid', '=', 'i.indrelid')->whereRaw('a.attnum = i.indkey[0]'))
            ->whereRaw('i.indrelid = to_regclass(quote_ident(?))', [$table])
            ->where('i.indisunique', true)
            ->where('i.indisprimary', false)
            ->where('i.indnatts', 1)
            ->whereNull('i.indpred')
            ->where('a.attname', $column)
            ->exists();
    }

}
