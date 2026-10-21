<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Exceptions\ServiceException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class EnvelopeMiddleware {

    /**
     * @param array<string, mixed> $extra
     */
    public static function json(int $code, string $error, array $extra = []): JsonResponse {
        return response()->json(['success' => false, 'code' => $code, 'error' => $error, 'message' => i18n("errors.{$error}")] + $extra);
    }

    public static function raw(Route $route): Route {
        return $route->setAction([...$route->getAction(), 'raw' => true]);
    }

    public function handle(Request $request, Closure $next): mixed {
        $response = $next($request);

        if (!$response instanceof Response && !$response instanceof JsonResponse) {
            return $response;
        }

        if (!$response->exception instanceof Throwable) {
            return $response;
        }

        $envelope = $this->envelope($response->exception);
        $route = $request->route();

        return $route instanceof Route && $route->getAction('raw') === true ? response('', intval($envelope->getData(true)['code'])) : $envelope;
    }

    private function envelope(Throwable $exception): JsonResponse {
        return match (true) {
            $exception instanceof ValidationException => self::json(422, 'validation-failed', ['fields' => validation_fields($exception)]),
            $exception instanceof ModelNotFoundException => self::json(404, 'data-not-found'),
            $exception instanceof ServiceException => self::json($exception->getCode(), $exception->getError(), $exception->getExtra()),
            $exception instanceof HttpExceptionInterface => self::json($exception->getStatusCode(), $exception->getStatusCode() === 429 ? 'too-many-requests' : 'request-failed'),
            default => self::json(500, 'server-error')
        };
    }

}
