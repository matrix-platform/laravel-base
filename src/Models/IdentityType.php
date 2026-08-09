<?php //>

namespace MatrixPlatform\Models;

enum IdentityType: string {

    case Member = 'Member';
    case User = 'User';
    case Vendor = 'Vendor';

    public function bundle(): string {
        return match ($this) {
            self::Member => 'member',
            self::User => 'admin',
            self::Vendor => 'vendor'
        };
    }

    public function cookie(): string {
        return 'matrix-' . strtolower($this->value);
    }

}
