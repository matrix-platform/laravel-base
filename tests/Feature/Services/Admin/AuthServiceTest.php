<?php //>

namespace Tests\Feature\Services\Admin;

use Illuminate\Support\Facades\Cache;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Services\Admin\AuthService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class AuthServiceTest extends FeatureTestCase {

    public function test_changing_password_with_the_wrong_current_one_reports_the_current_field(): void {
        $user = UserFactory::new()->createOne();

        try {
            app(AuthService::class)->passwd($user, 'not-the-real-password', 'new-Passw0rd1', null);
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => ['current' => ['invalid-password']]], $exception->getExtra());

            return;
        }

        $this->fail('the password change was expected to be rejected');
    }

    public function test_logging_in_with_the_wrong_captcha_code_reports_the_code_field(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-image']);

        Cache::put('captcha:test-token', hash('sha256', 'ABCDE'), 60);

        try {
            app(AuthService::class)->login('someone', 'whatever', 'test-token', 'WRONG', null);
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => ['code' => ['invalid-captcha']]], $exception->getExtra());

            return;
        }

        $this->fail('the login was expected to be rejected');
    }

    public function test_logging_in_without_a_captcha_code_succeeds_when_the_captcha_provider_is_disabled(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-none']);

        $user = UserFactory::new()->createOne();

        $data = app(AuthService::class)->login($user->username, 'secret-Passw0rd', '', '', null);

        $this->assertArrayHasKey('token', $data);
    }

    public function test_captcha_returns_null_when_the_captcha_provider_is_disabled(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-none']);

        $this->assertNull(app(AuthService::class)->captcha());
    }

    public function test_a_captcha_provider_that_resolves_to_no_driver_is_refused_instead_of_disabling_the_captcha(): void {
        config(['matrix.admin-captcha-provider' => 'does-not-exist']);

        $this->refuses('invalid-captcha-driver', fn () => app(AuthService::class)->captcha());
    }

    public function test_a_login_is_refused_rather_than_let_through_when_the_captcha_provider_resolves_to_no_driver(): void {
        config(['matrix.admin-captcha-provider' => 'does-not-exist']);

        $user = UserFactory::new()->createOne();

        $this->refuses('invalid-captcha-driver', fn () => app(AuthService::class)->login($user->username, 'secret-Passw0rd', '', '', null));
    }

    public function test_logging_in_with_the_wrong_username_or_password_reports_the_password_field(): void {
        $user = UserFactory::new()->createOne();

        Cache::put('captcha:test-token', hash('sha256', 'ABCDE'), 60);

        try {
            app(AuthService::class)->login($user->username, 'not-the-real-password', 'test-token', 'ABCDE', null);
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => ['password' => ['invalid-username-or-password']]], $exception->getExtra());

            return;
        }

        $this->fail('the login was expected to be rejected');
    }

    public function test_logging_out_with_an_unknown_token_leaves_the_live_session_alone(): void {
        $token = UserFactory::new()->createOne()->createToken();

        app(AuthService::class)->logout('not-a-real-token');

        $this->assertNotNull(AuthToken::findByToken($token, IdentityType::User));
    }

}
