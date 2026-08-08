<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends Controller {

    /**
     * @param array<string, mixed> $parameters
     */
    public function callAction($method, $parameters): Response {
        $data = DB::transaction(fn () => $this->{$method}(...array_values($parameters)));

        return $data instanceof Response ? $data : response()->json(['success' => true, 'data' => $data]);
    }

}
