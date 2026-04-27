<?php //>

namespace MatrixPlatform\Services;

use getID3;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatrixPlatform\Models\File;
use MatrixPlatform\Support\RollbackCallbacks;

class FileService {

    public function update($path, $name, $description) {
        $file = File::where('path', $path)->firstOrFail();
        $file->name = $name;
        $file->description = $description;
        $file->save();

        return $file;
    }

    public function upload($file, $privilege = 0, $maxSize = 0, $patterns = []) {
        if ($patterns && !array_filter($patterns, fn ($pattern) => preg_match($pattern, $file->getMimeType()))) {
            error('invalid-mime-type');
        }

        $size = $file->getSize();

        if ($maxSize > 0 && $size > $maxSize) {
            error('file-too-large');
        }

        $hash = hash_file('sha256', $file->getRealPath());

        $existing = File::where('hash', $hash)
            ->where('size', $size)
            ->where('privilege', $privilege)
            ->first();

        if ($existing) {
            return $existing;
        }

        $disk = $privilege ? config('matrix.file-private-disk') : config('matrix.file-public-disk');
        $path = $this->store($file, $disk);

        app(RollbackCallbacks::class)->register(fn () => Storage::disk($disk)->delete($path));

        $record = new File;
        $record->name = $file->getClientOriginalName();
        $record->path = Str::after($path, 'files/');
        $record->size = $size;
        $record->hash = $hash;
        $record->mime_type = $file->getMimeType();
        $record->privilege = $privilege;
        $record->user_id = user()->id;
        $record->member_id = member()->id;

        switch (strtok($record->mime_type, '/')) {
            case 'image':
                $info = getimagesize($file->getRealPath());
                if ($info) {
                    $record->width = $info[0];
                    $record->height = $info[1];
                }
                break;

            case 'audio':
            case 'video':
                $info = (new getID3)->analyze($file->getRealPath());
                if (isset($info['video']['resolution_x'])) {
                    $record->width = intval($info['video']['resolution_x']);
                    $record->height = intval($info['video']['resolution_y']);
                }
                if (isset($info['playtime_seconds'])) {
                    $record->seconds = intval($info['playtime_seconds']);
                }
                break;
        }

        $record->save();

        return $record;
    }

    private function store($file, $disk) {
        $ext = strtolower($file->getClientOriginalExtension());
        $folder = 'files/' . date('Ym');
        $name = Str::random(32);
        $path = $ext ? "{$folder}/{$name}.{$ext}" : "{$folder}/{$name}";

        Storage::disk($disk)->putFileAs($folder, $file, basename($path));

        return $path;
    }

}
