<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

    public static function supports(string $endpoint): bool {
        $host = parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);

        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || !is_string($host) || ($port !== null && $port !== 443)) {
            return false;
        }

        $hosts = config('matrix.webpush-hosts');

        foreach (is_array($hosts) ? $hosts : [] as $pattern) {
            if (is_string($pattern) && Str::is(strtolower($pattern), strtolower($host))) {
                return true;
            }
        }

        return false;
    }

    protected array $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_push_subscription';

}
