<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Builders\UserBuilder;
use MatrixPlatform\Models\Casts\PermissionMap;
use MatrixPlatform\Models\Declarations\UserDeclaration;

/**
 * @property int $id
 * @property string $username
 * @property ?string $password
 * @property ?int $group_id
 * @property ?string $secret
 * @property ?Carbon $confirmed_time
 * @property-read array<string, array<string, bool>> $permissions
 * @property-write array<string, mixed>|object|null $permissions
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property bool $disabled
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(UserDeclaration::class)]
class User extends BaseModel {

    const ROOT = 1;

    protected $attributes = [
        'disabled' => false,
        'permissions' => '{}'
    ];

    protected $hidden = [
        'password',
        'secret'
    ];

    protected $table = 'base_user';

    protected array $untraceable = ['password', 'secret'];

    public function createToken(): string {
        return AuthToken::issue(IdentityType::User, $this->id);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo {
        return $this->belongsTo(Group::class);
    }

    public function hasMfaEnabled(): bool {
        return $this->confirmed_time !== null;
    }

    public function newEloquentBuilder($query): UserBuilder {
        return new UserBuilder($query);
    }

    /**
     * @param array<string, mixed>|null $content
     */
    public function writeLog(UserLogType $type, ?array $content = null): void {
        $log = new UserLog();

        $log->user_id = $this->id;
        $log->type = $type;
        $log->content = $content;

        $log->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'confirmed_time' => 'datetime',
            'disable_time' => 'datetime',
            'disabled' => 'boolean',
            'enable_time' => 'datetime',
            'password' => 'hashed',
            'permissions' => PermissionMap::class,
            'secret' => 'encrypted'
        ];
    }

}
