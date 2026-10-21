<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as CurrentRequest;

class ConsoleRequest {

    private const ATTRIBUTE = 'matrix.console-request';

    public static function current(): bool {
        return CurrentRequest::instance()->attributes->get(self::ATTRIBUTE) === true;
    }

    public static function mark(Request $request): void {
        $request->attributes->set(self::ATTRIBUTE, true);
    }

}
