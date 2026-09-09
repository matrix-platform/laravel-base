<?php //>

namespace Tests\Stubs;

use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;

/**
 * @property int $id
 * @property ?string $title
 * @property ?list<array<string, mixed>> $attachments
 * @property ?string $translated__tw
 * @property ?string $translated__en
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class DriveGadget extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'stub_gadget';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'attachments' => 'array'
        ];
    }

}
