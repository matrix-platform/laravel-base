<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Models\Generators\CreatorAddress;

class Widget extends BaseModel {

    protected array $generators = [
        'ip' => CreatorAddress::class
    ];

    protected array $untraceable = ['secret'];

    protected $table = 'stub_widget';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'disable_time' => 'datetime',
            'enable_time' => 'datetime'
        ];
    }

}
