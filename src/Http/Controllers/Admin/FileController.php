<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\FileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends BaseController {

    public function __construct(private FileService $service) {}

    #[Action]
    public function download(Request $request): StreamedResponse {
        $request->validate([
            'path' => ['required', 'string']
        ]);

        $file = $this->service->find($request->string('path')->value());

        return $this->stream($this->service->disk($file->privilege), $this->service->location($file), $file->name, $file->mime_type);
    }

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
     * @return array{path: string}
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

        return ['path' => $this->service->upload($file, $request->integer('privilege'), null, null, $this->optionalString($request, 'usage'))->path];
    }

}
