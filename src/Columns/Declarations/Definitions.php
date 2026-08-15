<?php //>

namespace MatrixPlatform\Columns\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Support\PermissionTree;

class Definitions {

    /**
     * @return array<string, Definition>
     */
    public static function auditings(bool $updated = true): array {
        $definitions = [
            'creator_id' => new Definition(ColumnType::Integer),
            'create_time' => new Definition(ColumnType::DateTime)
        ];

        if (!$updated) {
            return $definitions;
        }

        return array_merge($definitions, [
            'updater_id' => new Definition(ColumnType::Integer),
            'update_time' => new Definition(ColumnType::DateTime)
        ]);
    }

    /**
     * @return array<string, Definition>
     */
    public static function permissions(): array {
        return ['permissions' => new Definition(ColumnType::Json, 'permissions', [], PermissionTree::class)];
    }

    /**
     * @return array<string, Definition>
     */
    public static function primaryKey(): array {
        return ['id' => new Definition(ColumnType::Integer)];
    }

    /**
     * @return array<string, Definition>
     */
    public static function ranking(): array {
        return ['ranking' => new Definition(ColumnType::Integer)];
    }

    /**
     * @return array<string, Definition>
     */
    public static function schedules(): array {
        return [
            'enable_time' => new Definition(ColumnType::DateTime),
            'disable_time' => new Definition(ColumnType::DateTime)
        ];
    }

}
