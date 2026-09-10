<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use MatrixPlatform\Support\ApiEncryption;

/**
 * @property int $id
 * @property string $kid
 * @property string $public_key
 * @property string $private_key
 * @property ?Carbon $expire_time
 * @property ?int $creator_id
 * @property Carbon $create_time
 */
class EncryptionKey extends BaseModel {

    const TRACEABLE = false;
    const UPDATED_AT = null;
    const UPDATED_BY = null;

    public static function active(): ?self {
        return self::query()
            ->whereNull('expire_time')
            ->orderByDesc('id')
            ->first();
    }

    public static function findByKid(?string $kid): ?self {
        if ($kid === null || $kid === '') {
            return null;
        }

        return self::query()
            ->where('kid', $kid)
            ->whereNotExpired()
            ->first();
    }

    public static function issue(): self {
        $pair = ApiEncryption::generate();
        $key = new self();

        $key->kid = (string) Str::uuid();
        $key->public_key = $pair['public'];
        $key->private_key = $pair['private'];

        $key->save();

        return $key;
    }

    public static function rotate(int $grace): self {
        foreach (self::query()->whereExpired()->get() as $expired) {
            $expired->delete();
        }

        foreach (self::query()->whereNull('expire_time')->get() as $previous) {
            $previous->expire_time = now()->addSeconds($grace);
            $previous->save();
        }

        return self::issue();
    }

    protected $table = 'base_encryption_key';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'expire_time' => 'datetime',
            'private_key' => 'encrypted'
        ];
    }

}
