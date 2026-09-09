<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\File;
use MatrixPlatform\Services\Admin\DriveService;
use MatrixPlatform\Services\FileService;
use Symfony\Component\HttpFoundation\Response;

class FileController extends BaseController {

    public function __construct(private FileService $service) {}

    #[Action(transaction: false)]
    public function get(string $path): Response {
        $file = File::query()->where('path', $path)->first();

        if ($file === null) {
            error('data-not-found', 404);
        }

        if (str_starts_with($file->path, File::DRIVE_PREFIX)) {
            $node = DriveNode::withTrashed()->where('path', substr($file->path, strlen(File::DRIVE_PREFIX)))->first();

            if ($node === null) {
                error('data-not-found', 404);
            }

            return $this->stream(app(DriveService::class)->disk(), app(DriveService::class)->location($node), $node->name, $node->mime_type);
        }

        if ($file->privilege === File::PUBLIC) {
            return redirect(Storage::disk($this->service->disk(File::PUBLIC))->url($this->service->location($file)), 302);
        }

        error('data-not-found', 404);
    }

}
