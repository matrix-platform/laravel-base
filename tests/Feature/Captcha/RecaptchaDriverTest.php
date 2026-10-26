<?php //>

namespace Tests\Feature\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Services\Admin\AuthService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class RecaptchaDriverTest extends FeatureTestCase {

    /**
     * @return array{token: string}|array{mfa: true, challenge: string}
     */
    private function attempt(string $token = 'recaptcha-token'): array {
        $user = UserFactory::new()->createOne();

        return app(AuthService::class)->login($user->username, 'secret-Passw0rd', $token, '', null);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function siteverify(array $body, int $status = 200): void {
        Http::fake(['*' => Http::response($body, $status)]);
    }

    private function useRecaptcha(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-recaptcha']);

        $this->useCfg('captcha-recaptcha', ['secret' => 'test-secret']);
    }

    private function useHostnames(string $hostnames): void {
        config(['matrix.admin-captcha-provider' => 'captcha-recaptcha']);

        $this->useCfg('captcha-recaptcha', ['secret' => 'test-secret', 'hostnames' => $hostnames]);
    }

    public function test_a_token_minted_on_a_host_outside_the_allow_list_is_rejected(): void {
        $this->useHostnames('admin.example.com');
        $this->siteverify(['success' => true, 'action' => 'login', 'hostname' => 'attacker.example.net', 'score' => 0.9]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_token_minted_on_an_allowed_host_logs_the_user_in(): void {
        $this->useHostnames('staging.example.com admin.example.com');
        $this->siteverify(['success' => true, 'action' => 'login', 'hostname' => 'admin.example.com', 'score' => 0.9]);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_an_empty_allow_list_accepts_any_host(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'login', 'hostname' => 'anything.example.net', 'score' => 0.9]);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_a_token_scoring_above_the_threshold_logs_the_user_in(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'login', 'score' => 0.9]);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_a_token_scoring_below_the_threshold_reports_the_code_field(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'login', 'score' => 0.1]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_token_scoring_exactly_the_threshold_logs_the_user_in(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'login', 'score' => 0.5]);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_an_unverified_token_reports_the_code_field(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_token_issued_for_another_action_is_rejected(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'subscribe', 'score' => 0.9]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_response_without_a_score_is_rejected_even_at_a_zero_threshold(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-recaptcha']);

        $this->useCfg('captcha-recaptcha', ['secret' => 'test-secret', 'threshold' => 0]);
        $this->siteverify(['success' => true, 'action' => 'login']);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_missing_threshold_falls_back_to_the_default_and_rejects_a_low_score(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-recaptcha']);

        $this->useCfg('captcha-recaptcha', ['secret' => 'test-secret', 'threshold' => null]);
        $this->siteverify(['success' => true, 'action' => 'login', 'score' => 0.3]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_missing_threshold_falls_back_to_the_default_and_accepts_a_high_score(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-recaptcha']);

        $this->useCfg('captcha-recaptcha', ['secret' => 'test-secret', 'threshold' => null]);
        $this->siteverify(['success' => true, 'action' => 'login', 'score' => 0.7]);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_a_response_without_a_score_is_rejected(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'login']);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_the_login_is_blocked_when_the_siteverify_request_fails(): void {
        $this->useRecaptcha();
        $this->siteverify([], 500);

        $this->refuses('captcha-request-failed', fn () => $this->attempt());
    }

    public function test_the_request_posts_the_configured_secret_and_the_token_to_google(): void {
        $this->useRecaptcha();
        $this->siteverify(['success' => true, 'action' => 'login', 'score' => 0.9]);

        $this->attempt('token-from-the-widget');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://www.google.com/recaptcha/api/siteverify', $request->url());
            $this->assertSame('test-secret', $request['secret']);
            $this->assertSame('token-from-the-widget', $request['response']);

            return true;
        });
    }

    public function test_the_captcha_endpoint_issues_no_challenge_under_this_provider(): void {
        $this->useRecaptcha();

        $this->assertNull(app(AuthService::class)->captcha());
    }

    public function test_the_login_is_blocked_when_siteverify_is_unreachable(): void {
        $this->useRecaptcha();

        Http::fake(fn () => throw new ConnectionException('unreachable'));

        $this->refuses('captcha-request-failed', fn () => $this->attempt());
    }

}
