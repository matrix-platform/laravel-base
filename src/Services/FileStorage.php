<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorage {

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

    public function store(UploadedFile $file, string $disk, string $folder): string {
        $path = date('Ym') . '/' . Str::random(32);

        Storage::disk($disk)->putFileAs($folder, $file, $path);

        return $path;
    }

}
