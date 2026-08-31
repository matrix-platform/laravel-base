<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseController extends Controller {

    /**
     * @param array<string, mixed> $parameters
     */
    public function callAction($method, $parameters): Response {
        $data = DB::transaction(fn () => $this->{$method}(...array_values($parameters)));

        return $data instanceof Response ? $data : response()->json(['success' => true, 'data' => $data]);
    }

    protected function optionalString(Request $request, string $key): ?string {
        return $request->filled($key) ? $request->string($key)->value() : null;
    }

    protected function stream(string $disk, string $location, string $name, ?string $mimeType): StreamedResponse {
        return Storage::disk($disk)->response($location, $name, ['Content-Type' => $mimeType === null ? 'application/octet-stream' : $mimeType]);
    }

}
