<?php //>

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

class IndexTest extends FeatureTestCase {

    /**
     * @return array<string, array{string, string}>
     */
    public static function lookups(): array {
        return [
            'manipulation log by record' => ['base_manipulation_log', 'base_manipulation_log_data_id_index'],
            'member log by member' => ['base_member_log', 'base_member_log_member_id_index'],
            'vendor log by vendor' => ['base_vendor_log', 'base_vendor_log_vendor_id_index'],
            'push log by member' => ['base_push_log', 'base_push_log_member_id_index'],
            'auth token by owner' => ['base_auth_token', 'base_auth_token_type_target_id_index'],
            'user log by user' => ['base_user_log', 'base_user_log_user_id_index'],
            'passkey credential by user' => ['base_passkey_credential', 'base_passkey_credential_user_id_index'],
            'city area by city' => ['base_city_area', 'base_city_area_city_id_index'],
            'menu by parent' => ['base_menu', 'base_menu_parent_id_index'],
            'block by page' => ['base_block', 'base_block_page_id_index'],
            'block item by block' => ['base_block_item', 'base_block_item_block_id_index']
        ];
    }

    #[DataProvider('lookups')]
    public function test_a_lookup_column_is_indexed(string $table, string $index): void {
        $this->assertTrue(DB::table('pg_indexes')->where('tablename', $table)->where('indexname', $index)->exists());
    }

    public function test_the_indexes_roll_back_and_come_back(): void {
        $migration = require dirname(__DIR__, 3) . '/database/migrations/0008_indexes.php';
        $names = array_column(self::lookups(), 1);

        $migration->down();

        $this->assertSame(0, DB::table('pg_indexes')->whereIn('indexname', $names)->count());

        $migration->up();

        $this->assertSame(count($names), DB::table('pg_indexes')->whereIn('indexname', $names)->count());
    }

}
