<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\VendorLogDeclaration;
use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

/**
 * @property int $id
 * @property int $vendor_id
 * @property string $type
 * @property ?array<string, mixed> $content
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?int $creator_id
 * @property Carbon $create_time
 */
#[Declared(VendorLogDeclaration::class)]
class VendorLog extends BaseModel {

    const TRACEABLE = false;
    const UPDATED_AT = null;
    const UPDATED_BY = null;

    protected array $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_vendor_log';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'content' => 'array'
        ];
    }

}
