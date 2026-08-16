<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\FileDeclaration;

/**
 * @property int $id
 * @property string $name
 * @property string $path
 * @property int $size
 * @property string $hash
 * @property ?string $description
 * @property ?string $mime_type
 * @property ?int $width
 * @property ?int $height
 * @property ?int $seconds
 * @property int $privilege
 * @property ?string $usage
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(FileDeclaration::class)]
class File extends BaseModel {

    const PRIVATE = 1;
    const PUBLIC = 0;

    protected $table = 'base_file';

}
