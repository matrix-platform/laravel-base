<?php //>

namespace MatrixPlatform\Models;

class CityArea extends BaseModel {

    protected $alias = 'areas';

    protected $parent = 'city';

    protected $table = 'base_city_area';

    public function city() {
        return $this->belongsTo(City::class, 'city_id');
    }

}
