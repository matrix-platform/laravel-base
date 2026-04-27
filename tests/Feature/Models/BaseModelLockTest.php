<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class BaseModelLockTest extends FeatureTestCase {

    private function createUser() {
        return User::forceCreate(['disabled' => false, 'username' => 'alice'])->fresh();
    }

    public function test_lock_returns_self_when_data_is_unchanged() {
        $user = $this->createUser();

        $result = DB::transaction(fn () => $user->lock());

        $this->assertSame($user, $result);
    }

    public function test_lock_throws_when_data_was_modified_concurrently() {
        $user = $this->createUser();

        DB::table('base_user')->where('id', $user->id)->update(['username' => 'bob']);

        $this->expectException(ServiceException::class);

        DB::transaction(fn () => $user->lock());
    }

    public function test_lock_throws_with_data_conflicted_error_key() {
        $user = $this->createUser();

        DB::table('base_user')->where('id', $user->id)->update(['username' => 'bob']);

        try {
            DB::transaction(fn () => $user->lock());
            $this->fail('Expected ServiceException was not thrown');
        } catch (ServiceException $e) {
            $this->assertEquals('data-conflicted', $e->getError());
        }
    }

    public function test_lock_does_not_modify_attributes() {
        $user = $this->createUser();
        $originalAttributes = $user->getAttributes();

        DB::transaction(fn () => $user->lock());

        $this->assertEquals($originalAttributes, $user->getAttributes());
    }

    public function test_lock_does_not_modify_original_snapshot() {
        $user = $this->createUser();
        $originalSnapshot = $user->getRawOriginal();

        DB::transaction(fn () => $user->lock());

        $this->assertEquals($originalSnapshot, $user->getRawOriginal());
    }

}
