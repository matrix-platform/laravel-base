<?php //>

namespace MatrixPlatform\Columns\Declarations;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\Block;

class BlockItemTypeResolver implements TypeResolver {

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
