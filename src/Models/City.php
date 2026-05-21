<?php //>

namespace MatrixPlatform\Models;

class City extends BaseModel {

    protected $table = 'base_city';

    public function areas() {
        return $this->hasMany(CityArea::class, 'city_id')->orderBy('ranking');
    }

}
