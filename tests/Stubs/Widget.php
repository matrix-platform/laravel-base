<?php //>

namespace Tests\Stubs;

use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Models\Generators\CreatorAddress;

/**
 * @property int $id
 * @property ?string $title
 * @property ?string $secret
 * @property ?string $ip
 * @property int $ranking
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
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
