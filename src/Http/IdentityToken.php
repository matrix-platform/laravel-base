<?php //>

namespace MatrixPlatform\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MatrixPlatform\Models\IdentityType;

class IdentityToken {

    public static function attach(JsonResponse $response, IdentityType $type, string $token): JsonResponse {
        return self::cookie($response, $type->cookie(), $token, 0);
    }

    public static function cookie(JsonResponse $response, string $name, string $token, int $minutes): JsonResponse {
        return $response->cookie($name, $token, $minutes, '/', null, (bool) config('session.secure'), true, false, 'lax');
    }

    public static function from(Request $request, IdentityType $type): ?string {
        $cookie = $request->cookie($type->cookie());

        return (is_string($cookie) ? $cookie : null) ?: $request->bearerToken();
    }

}
