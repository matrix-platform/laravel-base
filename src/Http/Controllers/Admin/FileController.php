<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\FileService;

class FileController extends BaseController {

    public function __construct(private FileService $service) {}

    #[Action]
    public function update(Request $request) {
        $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string']
        ]);

        return $this->service->update($request->path, $request->name, $request->description);
    }

    #[Action]
    public function upload(Request $request) {
        $request->validate([
            'file' => ['required', 'file'],
            'privilege' => ['required', 'integer']
        ]);

        $file = $this->service->upload($request->file('file'), $request->integer('privilege'));

        return ['path' => $file->path];
    }

}
