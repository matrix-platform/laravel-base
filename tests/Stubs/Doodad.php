<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Models\BaseModel;

/**
 * @property int $id
 * @property ?string $title
 * @property ?string $ip
 */
class Doodad extends BaseModel {

    protected array $generators = [
        'ip' => Restamper::class
    ];

    protected $table = 'stub_widget';

}
