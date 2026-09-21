<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\BlockItemDeclaration;

/**
 * @property int $id
 * @property int $block_id
 * @property string $title
 * @property ?array<string, mixed> $data
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(BlockItemDeclaration::class)]
class BlockItem extends BaseModel {

    protected $table = 'base_block_item';

    /**
     * @return BelongsTo<Block, $this>
     */
    public function block(): BelongsTo {
        return $this->belongsTo(Block::class, 'block_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'data' => 'array',
            'disable_time' => 'datetime',
            'enable_time' => 'datetime'
        ];
    }

}
