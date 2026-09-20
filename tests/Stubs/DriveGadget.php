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
 * @property ?list<array<string, mixed>> $gallery__tw
 * @property ?list<array<string, mixed>> $gallery__en
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
        $casts = ['attachments' => 'array'];

        foreach (locales() as $locale) {
            $casts["gallery__{$locale}"] = 'array';
        }

        return $casts;
    }

}
