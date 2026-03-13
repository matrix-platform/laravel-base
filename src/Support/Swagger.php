<?php //>

namespace MatrixPlatform\Support;

use OpenApi\Generator;

class Swagger {

    public static function generate() {
        $folders = [base_path() . '/app/Http/Controllers'];

        return (new Generator())->generate($folders);
    }

}
