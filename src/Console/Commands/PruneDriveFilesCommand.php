<?php //>

namespace MatrixPlatform\Console\Commands;

use Generator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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
            ->reject(fn (File $file) => in_array($file->path, $referenced, true));

        foreach ($files as $file) {
            $file->delete();
        }

        $this->info("Deleted {$files->count()} unreferenced drive-linked files");

        return self::SUCCESS;
    }

    /**
     * @return Generator<int, array{0: class-string<Model>, 1: string}>
     */
    private function driveColumns(): Generator {
        foreach (app(PackageRegistry::class)->models() as $model) {
            if (!is_a($model, Model::class, true)) {
                continue;
            }

            $definitions = app(MetadataRegistry::class)->definitions($model);

            if ($definitions === null) {
                continue;
            }

            foreach ($definitions as $field => $definition) {
                if (in_array($definition->presentation, [Presentation::DriveFile, Presentation::DriveImage], true)) {
                    yield [$model, $field];
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function referencedPaths(): array {
        $columns = [];

        foreach ($this->driveColumns() as [$model, $column]) {
            $columns[$model][] = $column;
        }

        $paths = [];

        foreach ($columns as $model => $fields) {
            foreach ($model::query()->get($fields) as $row) {
                foreach ($fields as $field) {
                    foreach ((array) $row->{$field} as $entry) {
                        if (is_array($entry) && array_key_exists('path', $entry)) {
                            $paths[] = $entry['path'];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

}
