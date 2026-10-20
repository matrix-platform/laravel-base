<?php //>

namespace MatrixPlatform\Columns\Declarations;

use Illuminate\Database\Eloquent\Model;

class BlockTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        $stored = $model?->getAttribute('type');

        if (is_string($stored)) {
            return $stored;
        }

        return is_array($input) && is_string(array_get_value($input, 'type')) ? $input['type'] : null;
    }

}
