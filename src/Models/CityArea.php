<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\CityAreaDeclaration;

/**
 * @property int $id
 * @property int $city_id
 * @property ?string $title__tw
 * @property ?string $title__en
 * @property string $post_code
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(CityAreaDeclaration::class)]
class CityArea extends BaseModel {

    protected $table = 'base_city_area';

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo {
        return $this->belongsTo(City::class, 'city_id');
    }

}
