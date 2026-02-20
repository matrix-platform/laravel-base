<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Exceptions\ServiceException;
use Throwable;

abstract class BaseController extends Controller {

    public function callAction($method, $parameters) {
        try {
            return DB::transaction(fn () => parent::callAction($method, $parameters));
        } catch (ServiceException $exception) {
            return response()->json(['success' => false, 'code' => $exception->getCode(), 'error' => $exception->getError(), 'message' => $exception->getMessage()]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'code' => 422, 'errors' => array_map(fn ($failures) => array_keys($failures), $exception->validator->failed())]);
        } catch (Throwable $throwable) {
            return response()->json(['success' => false, 'code' => 500, 'message' => $throwable->getMessage()]);
        }
    }

}
