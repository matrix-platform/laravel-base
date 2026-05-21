<?php //>

namespace MatrixPlatform\Models;

class Menu extends BaseModel {

    protected $casts = [
        'data' => 'array'
    ];

    protected $table = 'base_menu';

}
