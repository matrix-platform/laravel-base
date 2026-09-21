<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\TypeResolver;

class TestBlockTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        $stored = $model?->getAttribute('type');

        if (is_string($stored)) {
            return $stored;
        }

        return is_array($input) && is_string(array_get_value($input, 'type')) ? $input['type'] : null;
    }

}
