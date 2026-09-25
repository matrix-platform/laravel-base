<?php //>

namespace MatrixPlatform\Columns\Declarations;

use MatrixPlatform\Support\PermissionTree;

class Definitions {

    /**
     * @return array<string, Definition>
     */
    public static function auditings(bool $updated = true): array {
        $definitions = [
            'creator_id' => Definition::integer(tab: 'other'),
            'create_time' => Definition::dateTime(tab: 'other')
        ];

        if (!$updated) {
            return $definitions;
        }

        return array_merge($definitions, [
            'updater_id' => Definition::integer(tab: 'other'),
            'update_time' => Definition::dateTime(tab: 'other')
        ]);
    }

    /**
     * @return array<string, Definition>
     */
    public static function disabled(): array {
        return ['disabled' => Definition::boolean()];
    }

    /**
     * @return array<string, Definition>
     */
    public static function permissions(): array {
        return ['permissions' => Definition::json('permissions', [], PermissionTree::class)];
    }

    /**
     * @return array<string, Definition>
     */
    public static function primaryKey(): array {
        return ['id' => Definition::integer()];
    }

    /**
     * @return array<string, Definition>
     */
    public static function ranking(): array {
        return ['ranking' => Definition::integer()];
    }

    /**
     * @return array<string, Definition>
     */
    public static function schedules(): array {
        return [
            'enable_time' => Definition::dateTime(tab: 'other'),
            'disable_time' => Definition::dateTime(tab: 'other')
        ];
    }

    /**
     * @return array<string, Definition>
     */
    public static function title(bool $unique = false): array {
        return ['title' => Definition::text(translatable: true, required: true, unique: $unique)];
    }

}
