<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\TypeResolver;
use MatrixPlatform\Models\Block;

class TestBlockItemTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        $blockId = $model?->getAttribute('block_id');

        if ($blockId === null) {
            $route = request()->route();
            $blockId = $route === null ? null : $route->parameter('block_id');
        }

        if ($blockId === null) {
            return null;
        }

        return Block::query()->whereKey($blockId)->first()?->type;
    }

}
