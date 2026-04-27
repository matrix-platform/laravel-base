<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class BaseBuilderTest extends FeatureTestCase {

    private function insertToken(array $attrs = []) {
        DB::table('base_auth_token')->insert(array_merge([
            'token' => 'tok1',
            'type' => 'User',
            'target_id' => 1,
            'modify_time' => now(),
            'create_time' => now(),
            'expire_time' => null
        ], $attrs));
    }

    // whereActive

    public function test_where_active_includes_record_with_passed_enable_time_and_null_disable_time() {
        User::forceCreate(['username' => 'a', 'disabled' => false, 'enable_time' => now()->subMinute()]);

        $this->assertCount(1, User::whereActive()->get());
    }

    public function test_where_active_excludes_record_with_future_enable_time() {
        User::forceCreate(['username' => 'a', 'disabled' => false, 'enable_time' => now()->addMinute()]);

        $this->assertCount(0, User::whereActive()->get());
    }

    public function test_where_active_excludes_record_with_passed_disable_time() {
        User::forceCreate([
            'username' => 'a',
            'disabled' => false,
            'enable_time' => now()->subHour(),
            'disable_time' => now()->subMinute()
        ]);

        $this->assertCount(0, User::whereActive()->get());
    }

    public function test_where_active_uses_custom_column_names() {
        User::forceCreate([
            'username' => 'a',
            'disabled' => false,
            'enable_time' => now()->subMinute(),
            'disable_time' => null
        ]);

        $this->assertCount(1, User::whereActive('enable_time', 'disable_time')->get());
        $this->assertCount(0, User::whereActive('disable_time', 'enable_time')->get());
    }

    // whereExpired

    public function test_where_expired_includes_record_with_passed_expire_time() {
        $this->insertToken(['expire_time' => now()->subMinute()]);

        $this->assertCount(1, AuthToken::whereExpired()->get());
    }

    public function test_where_expired_excludes_record_with_null_expire_time() {
        $this->insertToken(['expire_time' => null]);

        $this->assertCount(0, AuthToken::whereExpired()->get());
    }

    public function test_where_expired_excludes_record_with_future_expire_time() {
        $this->insertToken(['expire_time' => now()->addMinute()]);

        $this->assertCount(0, AuthToken::whereExpired()->get());
    }

    // whereNotExpired

    public function test_where_not_expired_includes_record_with_null_expire_time() {
        $this->insertToken(['expire_time' => null]);

        $this->assertCount(1, AuthToken::whereNotExpired()->get());
    }

    public function test_where_not_expired_includes_record_with_future_expire_time() {
        $this->insertToken(['expire_time' => now()->addMinute()]);

        $this->assertCount(1, AuthToken::whereNotExpired()->get());
    }

    public function test_where_not_expired_excludes_record_with_passed_expire_time() {
        $this->insertToken(['expire_time' => now()->subMinute()]);

        $this->assertCount(0, AuthToken::whereNotExpired()->get());
    }

}
