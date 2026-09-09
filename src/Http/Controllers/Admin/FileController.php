<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\FileService;

class FileController extends BaseController {

    public function __construct(private FileService $service) {}

    #[Action]
    public function update(Request $request): JsonResponse {
        $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string']
        ]);

        $this->service->update($request->string('path')->value(), $request->string('name')->value(), $this->optionalString($request, 'description'));

        return response()->json(['success' => true]);
    }

    /**
     * @return array{name: string, path: string, width: ?int, height: ?int, seconds: ?int}
     */
    #[Action]
    public function upload(Request $request): array {
        $request->validate([
            'file' => ['required', 'file'],
            'privilege' => ['required', 'integer'],
            'usage' => ['nullable', 'string']
        ]);

        $file = $request->file('file');

        if (!$file instanceof UploadedFile) {
            error('validation-failed', 422);
        }

        $record = $this->service->upload($file, $request->integer('privilege'), null, null, $this->optionalString($request, 'usage'));

        return [
            'name' => $record->name,
            'path' => $record->path,
            'width' => $record->width,
            'height' => $record->height,
            'seconds' => $record->seconds
        ];
    }

}
