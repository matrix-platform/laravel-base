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
        $valid = $size !== null && $service->isRasterImage($mimeType) && $service->thumbnailWidth($size) !== null;

        if (!$valid) {
            return ['location' => $location, 'mime_type' => $mimeType];
        }

        $destination = $thumbnailLocation($size);

        $service->thumbnail(Storage::disk($disk)->path($location), Storage::disk($disk)->path($destination), $size);

        return ['location' => $destination, 'mime_type' => 'image/webp'];
    }

}
