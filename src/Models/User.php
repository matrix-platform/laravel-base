<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Builders\UserBuilder;
use MatrixPlatform\Models\Declarations\UserDeclaration;

/**
 * @property int $id
 * @property string $username
 * @property ?string $password
 * @property ?int $group_id
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
        'disabled' => false
    ];

    protected $hidden = [
        'password'
    ];

    protected $table = 'base_user';

    protected array $untraceable = ['password'];

    public function createToken(): string {
        $auth = new AuthToken();

        $auth->token = (string) Str::uuid();
        $auth->type = IdentityType::User;
        $auth->target_id = $this->id;

        $auth->save();
        $auth->keepAlive();

        return $auth->token;
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
            'disable_time' => 'datetime',
            'disabled' => 'boolean',
            'enable_time' => 'datetime',
            'password' => 'hashed'
        ];
    }

}
