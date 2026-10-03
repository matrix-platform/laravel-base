<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatrixPlatform\Support\RollbackCallbacks;

class FileStorage {

    const EXTENSIONS = [
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp'
    ];

    public function ensureDirectory(string $path): void {
        if (is_dir($path)) {
            return;
        }

        $this->ensureDirectory(dirname($path));

        if (!@mkdir($path) && !is_dir($path)) {
            error('directory-create-failed');
        }

        $mode = config('matrix.directory-permission');

        if (is_int($mode)) {
            chmod($path, $mode);
        }
    }

    public function hash(UploadedFile $file): string {
        $hash = hash_file('sha256', $file->getPathname());

        if ($hash === false) {
            error('request-failed');
        }

        return $hash;
    }

    public function location(string $folder, ?string $path): string {
        return $folder . $path;
    }

    public function requireLocal(string $disk): string {
        if (config("filesystems.disks.{$disk}.driver") !== 'local') {
            error('unsupported-disk-driver');
        }

        return $disk;
    }

    public function store(UploadedFile $file, string $disk, string $folder): string {
        $extension = array_get_value(self::EXTENSIONS, strval($file->getMimeType()));
        $path = date('Ym') . '/' . Str::random(32) . (is_string($extension) ? ".{$extension}" : '');

        $this->ensureDirectory(Storage::disk($disk)->path($folder . date('Ym')));

        if (Storage::disk($disk)->putFileAs($folder, $file, $path) === false) {
            error('file-write-failed');
        }

        app(RollbackCallbacks::class)->register(fn () => Storage::disk($disk)->delete($folder . $path));

        return $path;
    }

    public function thumbnailLocation(string $folder, ?string $path, string $size): string {
        return $this->location($folder, "{$size}/{$path}.webp");
    }

}
