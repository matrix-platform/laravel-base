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
        Cache::put('captcha:test-token', hash('sha256', 'ABCDE'), 60);

        try {
            app(AuthService::class)->login('someone', 'whatever', 'test-token', 'WRONG');
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => ['code' => ['invalid-captcha']]], $exception->getExtra());

            return;
        }

        $this->fail('the login was expected to be rejected');
    }

    public function test_logging_in_with_the_wrong_username_or_password_reports_the_password_field(): void {
        $user = UserFactory::new()->createOne();

        Cache::put('captcha:test-token', hash('sha256', 'ABCDE'), 60);

        try {
            app(AuthService::class)->login($user->username, 'not-the-real-password', 'test-token', 'ABCDE');
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
