<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\EncryptionKey;
use MatrixPlatform\Support\ApiEncryption;
use Symfony\Component\HttpFoundation\InputBag;

class EncryptedEnvelopeMiddleware {

    private const GROUPS = ['admin-api', 'api', 'vendor-api'];

    public function handle(Request $request, Closure $next): mixed {
        if (!$this->applies($request)) {
            return $next($request);
        }

        try {
            [$secret, $aad] = $this->unseal($request);
        } catch (ServiceException $exception) {
            return $this->rejected($exception);
        }

        return $this->sealed($next($request), $secret, $aad);
    }

    private function applies(Request $request): bool {
        if (!$request->isMethod('POST')) {
            return false;
        }

        foreach (self::GROUPS as $group) {
            $prefix = strval(config("matrix.{$group}-prefix"));

            if ($request->is("{$prefix}/*")) {
                return boolval(config("matrix.{$group}-encryption"));
            }
        }

        return false;
    }

    private function consume(string $epk): void {
        $window = intval(cfg('encryption.window'));

        if (!Cache::add('api-nonce:' . hash('sha256', $epk), true, $window * 2)) {
            error('invalid-envelope', 400);
        }
    }

    private function rejected(ServiceException $exception): JsonResponse {
        return EnvelopeMiddleware::json($exception->getCode(), $exception->getError(), $exception->getExtra());
    }

    private function rewrite(Request $request, string $plain): void {
        $data = json_decode($plain, true);

        if (!is_array($data)) {
            error('invalid-envelope', 400);
        }

        $request->initialize($request->query->all(), $data, $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $plain);
        $request->setJson(new InputBag($data));
    }

    private function sealed(mixed $response, string $secret, string $aad): mixed {
        if (!$response instanceof Response && !$response instanceof JsonResponse) {
            return $response;
        }

        $response->setContent(json_encode(ApiEncryption::seal($secret, strval($response->getContent()), $aad)));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function timestamp(array $data): int {
        $value = array_get_value($data, 'ts');

        if (!is_int($value) || abs(now()->getTimestamp() - $value) > intval(cfg('encryption.window'))) {
            error('invalid-envelope', 400);
        }

        return $value;
    }

    /**
     * @return array{string, string}
     */
    private function unseal(Request $request): array {
        $decoded = json_decode(strval($request->getContent()), true);
        $data = is_array($decoded) ? $decoded : [];
        $timestamp = $this->timestamp($data);
        $epk = $this->value($data, 'epk');
        $key = EncryptionKey::findByKid($this->value($data, 'kid'));

        if ($key === null) {
            error('invalid-envelope', 400);
        }

        $this->consume($epk);

        $path = ltrim($request->getBaseUrl() . $request->getPathInfo(), '/');
        $aad = "{$request->method()} {$path}|{$timestamp}";
        $secret = ApiEncryption::derive($key->private_key, $epk);

        $this->rewrite($request, ApiEncryption::open($secret, $this->value($data, 'iv'), $this->value($data, 'ct'), "request|{$aad}"));

        return [$secret, "response|{$aad}"];
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function value(array $data, string $field): string {
        $value = array_get_value($data, $field);

        if (!is_string($value) || $value === '') {
            error('invalid-envelope', 400);
        }

        return $value;
    }

}
