<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Builders\MemberBuilder;
use MatrixPlatform\Models\Declarations\MemberDeclaration;

/**
 * @property int $id
 * @property string $username
 * @property ?string $password
 * @property ?string $name
 * @property ?string $mobile
 * @property ?string $mail
 * @property ?string $avatar
 * @property int $status
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(MemberDeclaration::class)]
class Member extends BaseModel {

    const ENABLED = 1;

    protected $attributes = [
        'status' => self::ENABLED
    ];

    protected $hidden = [
        'password'
    ];

    protected $table = 'base_member';

    protected array $untraceable = ['password'];

    public function createToken(): string {
        return AuthToken::issue(IdentityType::Member, $this->id);
    }

    public function newEloquentBuilder($query): MemberBuilder {
        return new MemberBuilder($query);
    }

    /**
     * @param array<string, mixed>|null $content
     */
    public function writeLog(string $type, ?array $content = null): void {
        $log = new MemberLog();

        $log->member_id = $this->id;
        $log->type = $type;
        $log->content = $content;

        $log->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'password' => 'hashed'
        ];
    }

}
