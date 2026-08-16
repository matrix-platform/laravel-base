<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\AuthTokenDeclaration;
use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

/**
 * @property int $id
 * @property string $token
 * @property IdentityType $type
 * @property int $target_id
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?Carbon $expire_time
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(AuthTokenDeclaration::class)]
class AuthToken extends BaseModel {

    const TRACEABLE = false;

    public static function findByToken(?string $token, IdentityType $type): ?self {
        if ($token === null || $token === '') {
            return null;
        }

        return self::query()
            ->where('token', $token)
            ->where('type', $type)
            ->whereNotExpired()
            ->where('update_time', '>=', now()->subMinutes((int) cfg("{$type->bundle()}.token-idle-minutes")))
            ->first();
    }

    public static function issue(IdentityType $type, int $id): string {
        $auth = new self();

        $auth->token = (string) Str::uuid();
        $auth->type = $type;
        $auth->target_id = $id;

        $auth->save();
        $auth->keepAlive();

        return $auth->token;
    }

    protected array $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_auth_token';

    public function freshTimestamp(): Carbon {
        return now()->startOfMinute();
    }

    public function keepAlive(): void {
        if (!$this->update_time?->isCurrentMinute()) {
            $this->touch();
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'expire_time' => 'datetime',
            'type' => IdentityType::class
        ];
    }

}
