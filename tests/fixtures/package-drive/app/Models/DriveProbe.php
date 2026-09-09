<?php //>

namespace App\Models;

use MatrixPlatform\Models\BaseModel;

class DriveProbe extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'stub_widget';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'payload' => 'array'
        ];
    }

}
