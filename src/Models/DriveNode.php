<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ?int $parent_id
 * @property DriveNodeType $type
 * @property string $name
 * @property ?string $path
 * @property ?int $size
 * @property ?string $hash
 * @property ?string $description
 * @property ?string $mime_type
 * @property ?int $width
 * @property ?int $height
 * @property ?int $seconds
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 * @property ?Carbon $deleted_at
 */
class DriveNode extends BaseModel {

    use SoftDeletes;

    const ROOT = 0;

    protected $table = 'base_drive_node';

    /**
     * @return BelongsTo<DriveNode, $this>
     */
    public function parent(): BelongsTo {
        return $this->belongsTo(DriveNode::class, 'parent_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'deleted_at' => 'datetime',
            'type' => DriveNodeType::class
        ];
    }

}
