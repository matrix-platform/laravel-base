<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\BlockDeclaration;

/**
 * @property int $id
 * @property int $page_id
 * @property string $type
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
#[Declared(BlockDeclaration::class)]
class Block extends BaseModel {

    protected $table = 'base_block';

    /**
     * @return HasMany<BlockItem, $this>
     */
    public function items(): HasMany {
        return $this->hasMany(BlockItem::class, 'block_id');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo {
        return $this->belongsTo(Page::class, 'page_id');
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
