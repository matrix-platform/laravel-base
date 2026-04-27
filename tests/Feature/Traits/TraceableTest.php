<?php //>

namespace Tests\Feature\Traits;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class TraceableTest extends FeatureTestCase {

    private function logCount() {
        return DB::table('base_manipulation_log')->count();
    }

    private function lastLog() {
        return DB::table('base_manipulation_log')->latest('id')->first();
    }

    public function test_creating_inserts_log_with_type_1() {
        User::forceCreate(['username' => 'alice', 'disabled' => false]);

        $this->assertEquals(1, $this->logCount());
        $this->assertEquals(1, $this->lastLog()->type);
    }

    public function test_creating_log_current_contains_attributes() {
        User::forceCreate(['username' => 'alice', 'disabled' => false]);

        $current = json_decode($this->lastLog()->current, true);
        $this->assertEquals('alice', $current['username']);
    }

    public function test_creating_log_excludes_primary_key() {
        User::forceCreate(['username' => 'alice', 'disabled' => false]);

        $current = json_decode($this->lastLog()->current, true);
        $this->assertArrayNotHasKey('id', $current);
    }

    public function test_creating_log_excludes_untraceable_fields() {
        User::forceCreate(['username' => 'alice', 'password' => 'secret', 'disabled' => false]);

        $current = json_decode($this->lastLog()->current, true);
        $this->assertArrayNotHasKey('password', $current);
    }

    public function test_updating_inserts_log_with_type_2_and_only_changed_fields() {
        $user = User::forceCreate(['username' => 'alice', 'disabled' => false]);
        DB::table('base_manipulation_log')->truncate();

        $user->username = 'bob';
        $user->save();

        $log = $this->lastLog();
        $this->assertEquals(2, $log->type);

        $current = json_decode($log->current, true);
        $previous = json_decode($log->previous, true);
        $this->assertArrayHasKey('username', $current);
        $this->assertEquals('alice', $previous['username']);
        $this->assertEquals('bob', $current['username']);
        $this->assertArrayNotHasKey('disabled', $current);
    }

    public function test_updating_does_not_log_if_only_untraceable_fields_changed() {
        $user = User::forceCreate(['username' => 'alice', 'disabled' => false]);
        DB::table('base_manipulation_log')->truncate();

        $user->password = 'newpass';
        $user->save();

        $this->assertEquals(0, $this->logCount());
    }

    public function test_updating_mixed_fields_logs_only_traceable_changes() {
        $user = User::forceCreate(['username' => 'alice', 'disabled' => false]);
        $user->password = 'secret';
        $user->save();
        DB::table('base_manipulation_log')->truncate();

        $user->password = 'new-secret';
        $user->username = 'alice-renamed';
        $user->save();

        $log = $this->lastLog();
        $this->assertEquals(2, $log->type);

        $current = json_decode($log->current, true);
        $previous = json_decode($log->previous, true);

        $this->assertArrayHasKey('username', $current);
        $this->assertArrayNotHasKey('password', $current);
        $this->assertEquals('alice', $previous['username']);
        $this->assertEquals('alice-renamed', $current['username']);
    }

    public function test_deleting_inserts_log_with_type_3() {
        $user = User::forceCreate(['username' => 'alice', 'disabled' => false]);
        DB::table('base_manipulation_log')->truncate();

        $user->delete();

        $log = $this->lastLog();
        $this->assertEquals(3, $log->type);
        $this->assertNotNull(json_decode($log->previous, true));
        $this->assertNull($log->current);
    }

}
