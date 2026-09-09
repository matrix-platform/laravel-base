<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\DrivePermissionService;
use MatrixPlatform\Support\RollbackCallbacks;

class FileService {

    private const FOLDER = 'files/';

    public function bytes(string $value): int {
        $trimmed = trim($value);
        $unit = strtolower(substr($trimmed, -1));
        $number = (int) $trimmed;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number
        };
    }

    public function disk(int $privilege): string {
        return config()->string($privilege === File::PUBLIC ? 'matrix.file-public-disk' : 'matrix.file-private-disk');
    }

    public function find(string $path): File {
        return File::query()->where('path', $path)->firstOrFail();
    }

    public function location(File $file): string {
        return app(FileStorage::class)->location(self::FOLDER, $file->path);
    }

    public function maxUploadSize(): int {
        $limits = array_filter(
            [$this->bytes(strval(ini_get('upload_max_filesize'))), $this->bytes(strval(ini_get('post_max_size')))],
            fn (int $bytes): bool => $bytes > 0
        );

        return $limits === [] ? 0 : min($limits);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public function resolveDriveReferences(array $entries, User $actor): array {
        $ids = array_values(array_unique(array_map(
            fn (array $entry): int => (int) $entry['id'],
            array_filter($entries, fn (array $entry): bool => array_key_exists('id', $entry))
        )));
        $nodes = $ids === [] ? [] : DriveNode::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
        $paths = array_values(array_unique(array_map(fn (DriveNode $node): string => File::DRIVE_PREFIX . $node->path, $nodes)));
        $files = $paths === [] ? [] : File::query()->whereIn('path', $paths)->get()->keyBy('path')->all();
        $resolved = [];

        foreach ($entries as $entry) {
            if (!array_key_exists('id', $entry)) {
                $resolved[] = $entry;

                continue;
            }

            $node = array_get_value($nodes, (int) $entry['id']);

            if ($node === null || $node->type !== DriveNodeType::File || $node->size === null || $node->hash === null) {
                error('invalid-drive-file');
            }

            if (!app(DrivePermissionService::class)->allowed($node, $actor)) {
                error('permission-denied', 403);
            }

            $path = File::DRIVE_PREFIX . $node->path;
            $file = array_get_value($files, $path);

            if ($file === null) {
                $file = new File();

                $file->name = $node->name;
                $file->path = $path;
                $file->size = $node->size;
                $file->hash = $node->hash;
                $file->mime_type = $node->mime_type;
                $file->width = $node->width;
                $file->height = $node->height;
                $file->seconds = $node->seconds;
                $file->privilege = File::PRIVATE;

                $file->save();

                $files[$path] = $file;
            }

            $resolved[] = [
                'path' => $file->path,
                'name' => $node->name,
                'mime_type' => $node->mime_type,
                'size' => $node->size,
                'width' => $node->width,
                'height' => $node->height,
                'seconds' => $node->seconds
            ];
        }

        return $resolved;
    }

    public function update(string $path, string $name, ?string $description): File {
        $file = $this->find($path);

        $file->name = $name;
        $file->description = $description;

        $file->save();

        return $file;
    }

    /**
     * @param list<string>|null $patterns
     */
    public function upload(UploadedFile $file, int $privilege = File::PUBLIC, ?int $maxSize = null, ?array $patterns = null, ?string $usage = null): File {
        $mime = $file->getMimeType();
        $allowed = $patterns === null ? $this->patterns() : $patterns;
        $limit = $maxSize === null ? $this->limit() : $maxSize;
        $size = $file->getSize();

        if ($allowed !== [] && Arr::first($allowed, fn (string $pattern): bool => preg_match($pattern, strval($mime)) === 1) === null) {
            error('invalid-mime-type');
        }

        if ($limit > 0 && $size > $limit) {
            error('file-too-large');
        }

        $hash = app(FileStorage::class)->hash($file);

        $existing = File::query()
            ->where('hash', $hash)
            ->where('size', $size)
            ->where('privilege', $privilege)
            ->where('usage', $usage)
            ->first();

        if ($existing !== null && Storage::disk($this->disk($existing->privilege))->exists($this->location($existing))) {
            return $existing;
        }

        $disk = $this->disk($privilege);
        $path = app(FileStorage::class)->store($file, $disk, self::FOLDER);

        app(RollbackCallbacks::class)->register(fn () => Storage::disk($disk)->delete(self::FOLDER . $path));

        $record = new File();

        $record->name = $file->getClientOriginalName();
        $record->path = $path;
        $record->size = $size;
        $record->hash = $hash;
        $record->mime_type = $mime;
        $record->privilege = $privilege;
        $record->usage = $usage;

        $this->measure($record, $file->getPathname());

        $record->save();

        return $record;
    }

    private function limit(): int {
        return (int) cfg('file.max-size');
    }

    private function measure(File $record, string $path): void {
        $measured = app(MediaMeasurer::class)->measure($record->mime_type, $path);

        $record->width = $measured['width'];
        $record->height = $measured['height'];
        $record->seconds = $measured['seconds'];
    }

    /**
     * @return list<string>
     */
    private function patterns(): array {
        $patterns = cfg('file.mime-patterns');

        return tokenize(is_string($patterns) ? $patterns : null);
    }

}
