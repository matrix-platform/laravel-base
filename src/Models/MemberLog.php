<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\MemberLogDeclaration;
use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

/**
 * @property int $id
 * @property int $member_id
 * @property string $type
 * @property ?array<string, mixed> $content
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?int $creator_id
 * @property Carbon $create_time
 */
#[Declared(MemberLogDeclaration::class)]
class MemberLog extends BaseModel {

    const TRACEABLE = false;
    const UPDATED_AT = null;
    const UPDATED_BY = null;

    protected array $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_member_log';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'content' => 'array'
        ];
    }

}
