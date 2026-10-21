<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    /**
     * @var array<string, list<string>>
     */
    private array $indexes = [
        'base_auth_token' => ['type', 'target_id'],
        'base_block' => ['page_id'],
        'base_block_item' => ['block_id'],
        'base_city_area' => ['city_id'],
        'base_manipulation_log' => ['data_id'],
        'base_member_log' => ['member_id'],
        'base_menu' => ['parent_id'],
        'base_passkey_credential' => ['user_id'],
        'base_push_log' => ['member_id'],
        'base_user_log' => ['user_id'],
        'base_vendor_log' => ['vendor_id']
    ];

    public function up(): void {
        foreach ($this->indexes as $name => $columns) {
            Schema::table($name, fn (BaseBlueprint $table) => $table->index($columns));
        }
    }

    public function down(): void {
        foreach ($this->indexes as $name => $columns) {
            Schema::table($name, fn (BaseBlueprint $table) => $table->dropIndex($columns));
        }
    }

};
