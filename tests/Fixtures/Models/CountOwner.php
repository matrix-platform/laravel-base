<?php //>

namespace Tests\Fixtures\Models;

use MatrixPlatform\Models\BaseModel;

class CountOwner extends BaseModel {

    protected $alias = 'count-owner';
    protected $table = 'base_city';

    public function items() {
        return $this->hasMany(CountItem::class, 'city_id');
    }

}
