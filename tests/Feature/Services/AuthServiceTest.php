<?php //>

namespace Tests\Feature\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\AuthService;
use Tests\FeatureTestCase;

class AuthServiceTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);
    }

    private function service() {
        return app(AuthService::class);
    }

    private function validCaptcha() {
        $data = $this->service()->captcha();
        $token = $data['token'];
        $code = 'ABCDE';
        Cache::put("captcha:{$token}", hash('sha256', $code), cfg('admin.captcha-ttl'));
        return [$token, $code];
    }

    public function test_captcha_returns_token_and_image() {
        $data = $this->service()->captcha();

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('image', $data);
        $this->assertStringStartsWith('data:image/png;base64,', $data['image']);
    }

    public function test_captcha_stores_hash_in_cache() {
        $service = $this->service();
        $data = $service->captcha();

        $this->assertNotNull(Cache::get("captcha:{$data['token']}"));
    }

    public function test_login_throws_on_invalid_captcha() {
        $this->expectException(ServiceException::class);
        $this->service()->login('root@matrix', 'password', 'bad-token', 'bad-code');
    }

    public function test_login_throws_on_invalid_username() {
        [$token, $code] = $this->validCaptcha();
        $this->expectException(ServiceException::class);
        $this->service()->login('nobody', 'password', $token, $code);
    }

    public function test_login_throws_on_wrong_password() {
        [$token, $code] = $this->validCaptcha();
        $this->expectException(ServiceException::class);
        $this->service()->login('admin', 'wrongpass', $token, $code);
    }

    public function test_login_success_returns_token() {
        $user = User::find(User::ADMIN);
        $user->password = 'correct';
        $user->save();

        [$token, $code] = $this->validCaptcha();
        $result = $this->service()->login('admin', 'correct', $token, $code);

        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
    }

    public function test_login_success_writes_login_log() {
        $user = User::find(User::ADMIN);
        $user->password = 'correct';
        $user->save();

        [$token, $code] = $this->validCaptcha();
        $this->service()->login('admin', 'correct', $token, $code);

        $this->assertDatabaseHas('base_user_log', ['user_id' => User::ADMIN, 'type' => 'Login']);
    }

    public function test_login_throws_for_inactive_account() {
        $user = User::find(User::ADMIN);
        $user->password = 'correct';
        $user->enable_time = now()->addHour();
        $user->save();

        [$token, $code] = $this->validCaptcha();
        $this->expectException(ServiceException::class);
        $this->service()->login('admin', 'correct', $token, $code);
    }

    public function test_logout_expires_token() {
        $user = User::find(User::ADMIN);
        $authToken = $user->createToken();

        $this->service()->logout($authToken);

        $record = DB::table('base_auth_token')->where('token', $authToken)->first();
        $this->assertNotNull($record->expire_time);
        $this->assertLessThanOrEqual(now()->toDateTimeString(), $record->expire_time);
    }

    public function test_logout_with_unknown_token_does_not_throw() {
        $this->service()->logout('non-existent-token');
        $this->assertTrue(true);
    }

    public function test_passwd_throws_on_wrong_current_password() {
        $user = User::find(User::ADMIN);
        $user->password = 'correct';
        $user->save();

        $this->expectException(ServiceException::class);
        $this->service()->passwd(User::ADMIN,'wrong', 'newpassword');
    }

    public function test_passwd_updates_password() {
        $user = User::find(User::ADMIN);
        $user->password = 'correct';
        $user->save();

        $this->service()->passwd(User::ADMIN,'correct', 'newpassword');

        $this->assertTrue(Hash::check('newpassword', User::find(User::ADMIN)->password));
    }

    public function test_profile_returns_nodes_and_user() {
        $user = User::find(User::ROOT);
        $this->app['request']->setUserResolver(fn () => $user);

        $result = $this->service()->profile($user);

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertEquals($user->id, $result['profile']->id);
    }

}
