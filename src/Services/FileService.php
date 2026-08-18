<?php //>

namespace MatrixPlatform\Services;

use getID3;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        return self::FOLDER . $file->path;
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

        $hash = hash_file('sha256', $file->getPathname());

        if ($hash === false) {
            error('request-failed');
        }

        $existing = File::query()
            ->where('hash', $hash)
            ->where('size', $size)
            ->where('privilege', $privilege)
            ->where('usage', $usage)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $disk = $this->disk($privilege);
        $path = $this->store($file, $disk);

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
        match (strtok(strval($record->mime_type), '/')) {
            'image' => $this->measureImage($record, $path),
            'audio', 'video' => $this->measureMedia($record, $path),
            default => null
        };
    }

    private function measureImage(File $record, string $path): void {
        $info = getimagesize($path);

        if ($info === false) {
            return;
        }

        $record->width = $info[0];
        $record->height = $info[1];
    }

    private function measureMedia(File $record, string $path): void {
        $id3 = new getID3();

        $id3->option_tags_process = false;

        $info = $id3->analyze($path);
        $width = data_get($info, 'video.resolution_x');
        $height = data_get($info, 'video.resolution_y');
        $seconds = array_get_value($info, 'playtime_seconds');

        if (is_numeric($width) && is_numeric($height)) {
            $record->width = intval($width);
            $record->height = intval($height);
        }

        if (is_numeric($seconds)) {
            $record->seconds = intval($seconds);
        }
    }

    /**
     * @return list<string>
     */
    private function patterns(): array {
        $patterns = cfg('file.mime-patterns');

        return tokenize(is_string($patterns) ? $patterns : null);
    }

    private function store(UploadedFile $file, string $disk): string {
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::random(32);
        $path = date('Ym') . '/' . ($extension === '' ? $name : "{$name}.{$extension}");

        Storage::disk($disk)->putFileAs(self::FOLDER, $file, $path);

        return $path;
    }

}
