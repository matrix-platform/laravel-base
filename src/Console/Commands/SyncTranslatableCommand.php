<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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

        foreach (app(PackageRegistry::class)->models() as $model) {
            if (!is_a($model, Model::class, true)) {
                continue;
            }

            $definitions = app(MetadataRegistry::class)->definitions($model);

            if ($definitions === null) {
                continue;
            }

            $table = (new $model())->getTable();

            foreach ($definitions as $field => $definition) {
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
            $this->columns[$table] = DB::table('information_schema.columns')
                ->where('table_name', $table)
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
