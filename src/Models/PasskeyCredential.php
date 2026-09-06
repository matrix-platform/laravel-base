<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $credential_id
 * @property string $public_key
 * @property string $aaguid
 * @property int $sign_count
 * @property ?bool $uv_initialized
 * @property string $name
 * @property ?Carbon $last_used_time
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class PasskeyCredential extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'base_passkey_credential';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'last_used_time' => 'datetime',
            'uv_initialized' => 'boolean'
        ];
    }

}
