<?php //>

namespace MatrixPlatform\Http;

use Illuminate\Http\Request;
use MatrixPlatform\Models\IdentityType;

class IdentityToken {

    public static function from(Request $request, IdentityType $type): ?string {
        $cookie = $request->cookie($type->cookie());

        return (is_string($cookie) ? $cookie : null) ?: $request->bearerToken();
    }

}
