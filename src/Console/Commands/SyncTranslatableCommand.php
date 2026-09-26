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

        foreach (app(MetadataRegistry::class)->declaredModels(app(PackageRegistry::class)) as $model => $declares) {
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

        foreach (locales() as $locale) {
            $column = "{$field}__{$locale}";

            if (array_key_exists($column, $columns)) {
                continue;
            }

            DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");

            $this->columns[$table][$column] = $type;

            $this->info("Added {$table}.{$column} ({$type})");
        }
    }

}
