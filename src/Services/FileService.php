<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\File;
use MatrixPlatform\Support\RollbackCallbacks;

class FileService {

    private const FOLDER = 'files/';

    public function disk(int $privilege): string {
        return config()->string($privilege === File::PUBLIC ? 'matrix.file-public-disk' : 'matrix.file-private-disk');
    }

    public function find(string $path): File {
        return File::query()->where('path', $path)->firstOrFail();
    }

    public function location(File $file): string {
        return app(FileStorage::class)->location(self::FOLDER, $file->path);
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
