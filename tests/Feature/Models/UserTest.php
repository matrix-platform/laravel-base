<?php //>

namespace Tests\Feature\Models;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\UserLogType;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class UserTest extends FeatureTestCase {

    public function test_only_an_enabled_user_passes_the_builder_scope(): void {
        $user = UserFactory::new()->createOne();

        $this->assertNotNull(User::query()->whereEnabled()->whereKey($user->id)->first());

        $user->disabled = true;
        $user->save();

        $this->assertNull(User::query()->whereEnabled()->whereKey($user->id)->first());

        $user->disabled = false;
        $user->enable_time = now()->addDay();
        $user->save();

        $this->assertNull(User::query()->whereEnabled()->whereKey($user->id)->first());
    }

    public function test_usernames_are_keyed_by_id_and_skip_empty_ids(): void {
        $alpha = UserFactory::new()->createOne(['username' => 'alpha']);
        $beta = UserFactory::new()->createOne(['username' => 'beta']);

        $this->assertEquals([$alpha->id => 'alpha', $beta->id => 'beta'], User::usernames([$alpha->id, null, $beta->id, $alpha->id, 0]));
        $this->assertSame([], User::usernames([null, 0]));
    }

    public function test_the_password_and_secret_never_reach_the_audit_trail(): void {
        $user = UserFactory::new()->createOne();

        $user->password = 'another-Passw0rd';
        $user->secret = 'totp-seed';
        $user->save();

        $this->assertSame(0, ManipulationLog::query()->where('data_type', 'base_user')->where('data_id', $user->id)->where('type', ManipulationType::Updated)->count());
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('secret', $user->toArray());
    }

    private function sealedWithALostKey(): User {
        $user = UserFactory::new()->createOne(['name' => 'original', 'secret' => 'totp-seed']);

        Crypt::swap(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));

        return User::query()->findOrFail($user->id);
    }

    private function traced(User $user, ManipulationType $type): ManipulationLog {
        return ManipulationLog::query()
            ->where('data_type', 'base_user')
            ->where('data_id', $user->id)
            ->where('type', $type)
            ->sole();
    }

    public function test_a_secret_sealed_with_a_lost_key_does_not_block_an_update(): void {
        $user = $this->sealedWithALostKey();

        $user->name = 'renamed';
        $user->secret = null;
        $user->save();

        $log = $this->traced($user, ManipulationType::Updated);

        $this->assertSame(['name' => 'original'], (array) $log->before);
        $this->assertSame(['name' => 'renamed'], (array) $log->after);
    }

    public function test_a_secret_sealed_with_a_lost_key_does_not_block_a_delete(): void {
        $user = $this->sealedWithALostKey();

        $user->delete();

        $before = (array) $this->traced($user, ManipulationType::Deleted)->before;

        $this->assertSame('original', array_get_value($before, 'name'));
        $this->assertArrayNotHasKey('secret', $before);
    }

    public function test_logins_keep_only_the_login_logs_of_the_user(): void {
        $user = UserFactory::new()->createOne();
        $other = UserFactory::new()->createOne();
        $login = UserLog::forceCreate(['user_id' => $user->id, 'type' => UserLogType::Login]);

        UserLog::forceCreate(['user_id' => $user->id, 'type' => UserLogType::Logout]);
        UserLog::forceCreate(['user_id' => $other->id, 'type' => UserLogType::Login]);

        $this->assertSame([$login->id], $user->logins()->pluck('id')->all());
    }

}
