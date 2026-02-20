<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Exceptions\ServiceException;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends Controller {

    public function callAction($method, $parameters) {
        try {
            $data = DB::transaction(fn () => parent::callAction($method, $parameters));

            if ($data instanceof Response) {
                return $data;
            }

            return ['success' => true, 'data' => $data];
        } catch (ModelNotFoundException $exception) {
            return ['success' => false, 'code' => 500, 'error' => 'data-not-found', 'message' => i18n('errors.data-not-found')];
        } catch (ServiceException $exception) {
            return ['success' => false, 'code' => $exception->getCode(), 'error' => $exception->getError(), 'message' => $exception->getMessage()];
        } catch (ValidationException $exception) {
            return ['success' => false, 'code' => 422, 'errors' => array_map(fn ($failures) => array_keys($failures), $exception->validator->failed())];
        }
    }

}
