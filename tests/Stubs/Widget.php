<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Models\Generators\CreatorAddress;

/**
 * @property int $id
 * @property ?string $title
 * @property ?string $secret
 * @property ?string $ip
 * @property ?int $trinket_id
 * @property ?array<string, mixed> $payload
 * @property ?string $translated__tw
 * @property ?string $translated__en
 * @property int $ranking
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class Widget extends BaseModel {

    protected $hidden = [
        'secret'
    ];

    protected array $generators = [
        'ip' => CreatorAddress::class
    ];

    protected array $untraceable = ['secret'];

    protected $table = 'stub_widget';

    /**
     * @return MorphMany<Trinket, $this>
     */
    public function owned(): MorphMany {
        return $this->morphMany(Trinket::class, 'owner');
    }

    /**
     * @return BelongsTo<Trinket, $this>
     */
    public function pinned(): BelongsTo {
        return $this->belongsTo(Trinket::class, 'trinket_id');
    }

    /**
     * @return HasOne<Trinket, $this>
     */
    public function sole(): HasOne {
        return $this->hasOne(Trinket::class);
    }

    /**
     * @return BelongsToMany<Trinket, $this>
     */
    public function tagged(): BelongsToMany {
        return $this->belongsToMany(Trinket::class, 'stub_trinket_widget');
    }

    /**
     * @return HasMany<Trinket, $this>
     */
    public function trinkets(): HasMany {
        return $this->hasMany(Trinket::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'disable_time' => 'datetime',
            'enable_time' => 'datetime',
            'payload' => 'array'
        ];
    }

}
