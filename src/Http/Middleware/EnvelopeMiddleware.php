<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Exceptions\ServiceException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class EnvelopeMiddleware {

    public function handle(Request $request, Closure $next): mixed {
        $response = $next($request);

        if (!$response instanceof Response && !$response instanceof JsonResponse) {
            return $response;
        }

        return $response->exception instanceof Throwable ? $this->envelope($response->exception) : $response;
    }

    private function envelope(Throwable $exception): JsonResponse {
        return match (true) {
            $exception instanceof ValidationException => $this->json(422, 'validation-failed', ['fields' => $this->fields($exception)]),
            $exception instanceof ModelNotFoundException => $this->json(404, 'data-not-found'),
            $exception instanceof ServiceException => $this->json($exception->getCode(), $exception->getError()),
            $exception instanceof HttpExceptionInterface => $this->json($exception->getStatusCode(), $exception->getStatusCode() === 429 ? 'too-many-requests' : 'request-failed'),
            default => $this->json(500, 'server-error')
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private function fields(ValidationException $exception): array {
        return array_map(fn (array $rules): array => array_map(Str::kebab(...), array_keys($rules)), $exception->validator->failed());
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function json(int $code, string $error, array $extra = []): JsonResponse {
        return response()->json(array_merge(['success' => false, 'code' => $code, 'error' => $error, 'message' => i18n("errors.{$error}")], $extra));
    }

}
