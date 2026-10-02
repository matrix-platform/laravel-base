<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\TypeResolver;

class TestLockingMenuTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        $data = $model?->getAttribute('data');
        $kind = is_array($data) ? array_get_value($data, 'kind') : null;

        return is_string($kind) ? $kind : 'social';
    }

}
