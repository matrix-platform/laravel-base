<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\TypeResolver;

class TestPageTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        return 'page';
    }

}
