<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Models\File;
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
     * @return array{path: string, name: string, mime_type: ?string, size: int, width: ?int, height: ?int, seconds: ?int}
     */
    #[Action(encrypted: false)]
    public function upload(Request $request): array {
        $request->validate([
            'file' => ['required', 'file'],
            'privilege' => ['required', 'integer', Rule::in([File::PUBLIC, File::PRIVATE])],
            'usage' => ['nullable', 'string']
        ]);

        $file = $request->file('file');

        if (!$file instanceof UploadedFile) {
            error('validation-failed', 422);
        }

        $record = $this->service->upload($file, $request->integer('privilege'), null, null, $this->optionalString($request, 'usage'));

        return [
            'path' => $record->path,
            'name' => $record->name,
            'mime_type' => $record->mime_type,
            'size' => $record->size,
            'width' => $record->width,
            'height' => $record->height,
            'seconds' => $record->seconds
        ];
    }

}
