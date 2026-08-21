<?php //>

namespace MatrixPlatform\Support;

use MatrixPlatform\Models\User;

enum AdminLevel: int {

    case Admin = 2;
    case Regular = 3;
    case Root = 1;

    private const ADMIN_MAX_ID = 1000;

    public static function of(int $id): self {
        return match (true) {
            $id === User::ROOT => self::Root,
            $id <= self::ADMIN_MAX_ID => self::Admin,
            default => self::Regular
        };
    }

    public function minimumManageableId(): int {
        return match ($this) {
            self::Root => User::ROOT,
            self::Admin => User::ROOT + 1,
            self::Regular => self::ADMIN_MAX_ID + 1
        };
    }

}
