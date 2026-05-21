<?php //>

namespace Tests\Fixtures\Models;

use MatrixPlatform\Models\BaseModel;

class CountItem extends BaseModel {

    protected $alias = 'count-item';
    protected $parent = 'owner';
    protected $table = 'base_city_area';

    public function owner() {
        return $this->belongsTo(CountOwner::class, 'city_id');
    }

}
