<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Traits\Traceable;

class CityArea extends BaseModel {

    use Traceable;

    protected $table = 'base_city_area';

    public function city() {
        return $this->belongsTo(City::class, 'city_id');
    }

}
