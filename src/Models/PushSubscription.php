<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

/**
 * @property int $id
 * @property int $member_id
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property ?string $user_agent
 * @property ?string $ip
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class PushSubscription extends BaseModel {

    const TRACEABLE = false;

    protected array $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_push_subscription';

}
