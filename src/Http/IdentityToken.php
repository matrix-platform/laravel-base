<?php //>

namespace MatrixPlatform\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MatrixPlatform\Models\IdentityType;

class IdentityToken {

    public static function attach(JsonResponse $response, IdentityType $type, string $token): JsonResponse {
        return $response->cookie($type->cookie(), $token, 0, '/', null, (bool) config('session.secure'), true, false, 'lax');
    }

    public static function from(Request $request, IdentityType $type): ?string {
        $cookie = $request->cookie($type->cookie());

        return (is_string($cookie) ? $cookie : null) ?: $request->bearerToken();
    }

}
