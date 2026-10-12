<?php //>

namespace MatrixPlatform\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;

interface WritesStagedRows {

    /**
     * @return array{insert: bool, update: bool, delete: bool}
     */
    public function stagedAbilities(Model $parent): array;

    public function stagedDeleteRow(string $id): void;

    /**
     * @param array<array-key, mixed> $row
     */
    public function stagedInsertRow(array $row): Model;

    public function stagedPermissionTag(string $operation): string;

    /**
     * @param array<array-key, mixed> $row
     */
    public function stagedUpdateRow(string $id, array $row): Model;

}
