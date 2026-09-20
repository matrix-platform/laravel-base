<?php //>

namespace Tests\Feature\Captcha;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Captcha\NoneDriver;
use MatrixPlatform\Services\Admin\AuthService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class TurnstileDriverTest extends FeatureTestCase {

    /**
     * @return array{token: string}|array{mfa: true, challenge: string}
     */
    private function attempt(string $token = 'turnstile-token'): array {
        $user = UserFactory::new()->createOne();

        return app(AuthService::class)->login($user->username, 'secret-Passw0rd', $token, '', null);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function siteverify(array $body, int $status = 200): void {
        Http::fake(['*' => Http::response($body, $status)]);
    }

    private function useTurnstile(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-turnstile']);

        $this->useCfg('captcha-turnstile', ['secret' => 'test-secret']);
    }

    private function useHostnames(string $hostnames): void {
        config(['matrix.admin-captcha-provider' => 'captcha-turnstile']);

        $this->useCfg('captcha-turnstile', ['secret' => 'test-secret', 'hostnames' => $hostnames]);
    }

    // The site key is public, so an attacker can mint valid, well-scored tokens from a page they control. The hostname is the only
    // field that tells those apart from tokens minted on our own login page.
    public function test_a_token_minted_on_a_host_outside_the_allow_list_is_rejected(): void {
        $this->useHostnames('admin.example.com');
        $this->siteverify(['success' => true, 'action' => 'login', 'hostname' => 'attacker.example.net']);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    public function test_a_token_minted_on_an_allowed_host_logs_the_user_in(): void {
        $this->useHostnames('staging.example.com admin.example.com');
        $this->siteverify(['success' => true, 'action' => 'login', 'hostname' => 'admin.example.com']);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    // Shipping default: an empty list means the check is off, so upgrading does not lock anybody out.
    public function test_an_empty_allow_list_accepts_any_host(): void {
        $this->useTurnstile();
        $this->siteverify(['success' => true, 'action' => 'login', 'hostname' => 'anything.example.net']);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_a_verified_token_logs_the_user_in(): void {
        $this->useTurnstile();
        $this->siteverify(['success' => true, 'action' => 'login']);

        $this->assertArrayHasKey('token', $this->attempt());
    }

    public function test_an_unverified_token_reports_the_code_field(): void {
        $this->useTurnstile();
        $this->siteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    // A token minted on another page of the same site verifies successfully; only the action tells the two apart.
    public function test_a_token_issued_for_another_action_is_rejected(): void {
        $this->useTurnstile();
        $this->siteverify(['success' => true, 'action' => 'subscribe']);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    // Cloudflare omits the action entirely when the widget that minted the token never declared one — this is also the shape the
    // documented testing keys return. Absent must be rejected just like wrong: accepting it would let any actionless token in.
    public function test_a_response_without_an_action_is_rejected(): void {
        $this->useTurnstile();
        $this->siteverify(['success' => true]);

        $this->refusesField('code', 'invalid-captcha', fn () => $this->attempt());
    }

    // Fail-closed: a Cloudflare outage must not become an open door, and it must not be reported as a wrong answer either.
    public function test_the_login_is_blocked_when_the_siteverify_request_fails(): void {
        $this->useTurnstile();
        $this->siteverify([], 500);

        $this->refuses('captcha-request-failed', fn () => $this->attempt());
    }

    // The only assertion that pins what Cloudflare actually receives: a renamed key or a stray endpoint passes every other test.
    public function test_the_request_posts_the_configured_secret_and_the_token_to_cloudflare(): void {
        $this->useTurnstile();
        $this->siteverify(['success' => true, 'action' => 'login']);

        $this->attempt('token-from-the-widget');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://challenges.cloudflare.com/turnstile/v0/siteverify', $request->url());
            $this->assertSame('test-secret', $request['secret']);
            $this->assertSame('token-from-the-widget', $request['response']);

            return true;
        });
    }

    // The way back into the admin panel when Cloudflare is down, as written up in the README under
    // "驗證碼服務掛掉時怎麼進後台". It swaps the driver through base_resource_override, so nothing is deployed and
    // config/matrix.php is never touched. If this stops working the discovery happens at three in the morning.
    public function test_the_documented_override_lets_a_login_through_without_a_token(): void {
        config(['matrix.admin-captcha-provider' => 'captcha-turnstile']);

        $this->useCfg('captcha-turnstile', ['driver' => NoneDriver::class]);

        Http::fake();

        $user = UserFactory::new()->createOne();
        $data = app(AuthService::class)->login($user->username, 'secret-Passw0rd', '', '', null);

        $this->assertArrayHasKey('token', $data);

        Http::assertNothingSent();
    }

    public function test_the_captcha_endpoint_issues_no_challenge_under_this_provider(): void {
        $this->useTurnstile();

        $this->assertNull(app(AuthService::class)->captcha());
    }

}
