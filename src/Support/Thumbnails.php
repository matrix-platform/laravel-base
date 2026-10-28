<?php //>

namespace MatrixPlatform\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\FileService;

/**
 * @mixin BaseController
 */
trait Thumbnails {

    /**
     * @return array{location: string, mime_type: ?string}
     */
    protected function withThumbnail(Request $request, string $disk, string $location, ?string $mimeType, Closure $thumbnailLocation): array {
        $size = $this->optionalString($request, 'size');
        $service = app(FileService::class);

        if ($size === null || !$service->isRasterImage($mimeType) || $service->thumbnailWidth($size) === null) {
            return ['location' => $location, 'mime_type' => $mimeType];
        }

        $destination = $thumbnailLocation($size);
        $storage = Storage::disk($disk);

        $service->thumbnail($storage->path($location), $storage->path($destination), $size);

        return ['location' => $destination, 'mime_type' => 'image/webp'];
    }

}
