<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\File;
use MatrixPlatform\Services\Admin\DriveService;
use MatrixPlatform\Services\FileService;
use MatrixPlatform\Support\Thumbnails;
use Symfony\Component\HttpFoundation\Response;

class FileController extends BaseController {

    use Thumbnails;

    public function __construct(private FileService $service) {}

    #[Action(transaction: false)]
    public function get(Request $request, string $path): Response {
        $file = File::query()->where('path', $path)->first();

        if ($file === null) {
            error('data-not-found', 404);
        }

        if (str_starts_with($file->path, File::DRIVE_PREFIX)) {
            $node = DriveNode::withTrashed()->where('path', substr($file->path, strlen(File::DRIVE_PREFIX)))->first();

            if ($node === null) {
                error('data-not-found', 404);
            }

            $driveService = app(DriveService::class);
            $disk = $driveService->disk();

            $resolved = $this->withThumbnail(
                $request,
                $disk,
                $driveService->location($node),
                $node->mime_type,
                fn (string $size): string => $driveService->thumbnailLocation($node, $size)
            );

            return $this->stream($disk, $resolved['location'], $node->name, $resolved['mime_type']);
        }

        if ($file->privilege === File::PUBLIC) {
            $disk = $this->service->disk(File::PUBLIC);

            $resolved = $this->withThumbnail(
                $request,
                $disk,
                $this->service->location($file),
                $file->mime_type,
                fn (string $size): string => $this->service->thumbnailLocation($file, $size)
            );

            return redirect(Storage::disk($disk)->url($resolved['location']), 302);
        }

        error('data-not-found', 404);
    }

}
