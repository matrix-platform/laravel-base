<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Traits\Traceable;

class City extends BaseModel {

    use Traceable;

    protected $table = 'base_city';

    public function areas() {
        return $this->hasMany(CityArea::class, 'city_id')->orderBy('ranking');
    }

}
