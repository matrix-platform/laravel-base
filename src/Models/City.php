<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\CityDeclaration;

/**
 * @property int $id
 * @property ?string $title__tw
 * @property ?string $title__en
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(CityDeclaration::class)]
class City extends BaseModel {

    protected $table = 'base_city';

    /**
     * @return HasMany<CityArea, $this>
     */
    public function areas(): HasMany {
        return $this->hasMany(CityArea::class, 'city_id')->orderBy('ranking');
    }

}
