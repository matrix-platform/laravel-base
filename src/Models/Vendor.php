<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Builders\VendorBuilder;
use MatrixPlatform\Models\Declarations\VendorDeclaration;

/**
 * @property int $id
 * @property string $username
 * @property ?string $password
 * @property ?string $title
 * @property ?string $tax_id
 * @property ?string $contact
 * @property ?string $mobile
 * @property ?string $mail
 * @property int $status
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(VendorDeclaration::class)]
class Vendor extends BaseModel {

    const ENABLED = 1;

    protected static function booted(): void {
        parent::booted();

        static::deleting(fn (Vendor $vendor) => VendorLog::query()->where('vendor_id', $vendor->id)->get()->each->delete());
    }

    protected $attributes = [
        'status' => self::ENABLED
    ];

    protected $hidden = [
        'password'
    ];

    protected $table = 'base_vendor';

    protected array $untraceable = ['password'];

    public function createToken(): string {
        return AuthToken::issue(IdentityType::Vendor, $this->id);
    }

    /**
     * @return HasMany<VendorLog, $this>
     */
    public function logins(): HasMany {
        return $this->hasMany(VendorLog::class)->where('type', VendorLogType::Login->value);
    }

    public function newEloquentBuilder($query): VendorBuilder {
        return new VendorBuilder($query);
    }

    /**
     * @param array<string, mixed>|null $content
     */
    public function writeLog(string $type, ?array $content = null): void {
        $log = new VendorLog();

        $log->vendor_id = $this->id;
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
