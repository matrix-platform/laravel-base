<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Traits\Traceable;

class Menu extends BaseModel {

    use Traceable;

    protected $casts = ['data' => 'json'];
    protected $table = 'base_menu';

}
