<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Models\File;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;

class PruneDriveFilesCommand extends Command {

    protected $description = 'Delete drive-linked base_file records that are no longer referenced by any CRUD record';

    protected $signature = 'matrix:prune-drive-files';

    public function handle(): int {
        $referenced = $this->referencedPaths();

        $files = File::query()
            ->where('path', 'like', File::DRIVE_PREFIX . '%')
            ->get()
            ->reject(fn (File $file) => array_key_exists($file->path, $referenced));

        foreach ($files as $file) {
            $file->delete();
        }

        $this->info("Deleted {$files->count()} unreferenced drive-linked files");

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function referencedPaths(): array {
        $columns = [];

        foreach (app(MetadataRegistry::class)->declaredModels(app(PackageRegistry::class)) as $model => $declares) {
            foreach ($declares->definitions() as $field => $definition) {
                if (!in_array($definition->presentation, [Presentation::DriveFile, Presentation::DriveImage], true)) {
                    continue;
                }

                if (!$definition->translatable) {
                    $columns[$model][] = $field;

                    continue;
                }

                foreach (locales() as $locale) {
                    $columns[$model][] = "{$field}__{$locale}";
                }
            }
        }

        $paths = [];

        foreach ($columns as $model => $fields) {
            foreach ($model::query()->get($fields) as $row) {
                foreach ($fields as $field) {
                    foreach ((array) $row->{$field} as $entry) {
                        if (is_array($entry) && array_key_exists('path', $entry)) {
                            $paths[strval($entry['path'])] = true;
                        }
                    }
                }
            }
        }

        return $paths;
    }

}
