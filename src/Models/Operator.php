<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\OperatorDeclaration;

/**
 * @property int $id
 * @property IdentityType $type
 * @property string $username
 */
#[Declared(OperatorDeclaration::class)]
class Operator extends BaseModel {

    const TRACEABLE = false;

    /**
     * @param iterable<int, ?int> $ids
     * @return array<int, self>
     */
    public static function identities(iterable $ids): array {
        $unique = collect($ids)
            ->filter()
            ->unique()
            ->values();

        return $unique->isEmpty() ? [] : self::query()->whereIn('id', $unique)->get()->keyBy('id')->all();
    }

    public $timestamps = false;

    protected $table = 'base_operator';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'type' => IdentityType::class
        ];
    }

}
