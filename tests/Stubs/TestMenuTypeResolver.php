<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\TypeResolver;

class TestMenuTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        $parentId = is_array($input) && array_key_exists('parent_id', $input)
            ? $input['parent_id']
            : $model?->getAttribute('parent_id');

        return $parentId === null ? 'header' : 'social';
    }

}
