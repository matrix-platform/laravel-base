<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\UserLogType;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class AuthControllerTest extends FeatureTestCase {

    private const CODE = '13579';
    private const PASSWORD = 'secret-Passw0rd';

    private function captchaToken(): string {
        $token = 'captcha-token';

        Cache::put("captcha:{$token}", hash('sha256', self::CODE), 300);

        return $token;
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function login(string $username = 'alice', string $password = self::PASSWORD, string $code = self::CODE): TestResponse {
        return $this->postJson('admin/auth/login', [
            'username' => $username,
            'password' => $password,
            'token' => $this->captchaToken(),
            'code' => $code
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function user(array $attributes = []): User {
        return UserFactory::new()->createOne(array_merge(['username' => 'alice', 'password' => self::PASSWORD], $attributes));
    }

    public function test_a_successful_login_returns_a_token_and_sets_the_cookie(): void {
        $this->user();

        $response = $this->login();

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['token']]);
        $response->assertCookie('matrix-user');
    }

    public function test_a_bearer_token_reaches_a_protected_endpoint(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.profile.username', 'alice');
    }

    public function test_a_cookie_reaches_a_protected_endpoint(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withCredentials()
            ->withUnencryptedCookie('matrix-user', $token)
            ->postJson('admin/auth/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.profile.username', 'alice');
    }

    public function test_the_profile_carries_the_navigation_nodes(): void {
        $this->useMenuFixtures('authority');

        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/profile');

        $response->assertJsonPath('data.nodes.system.title', 'System');
        $response->assertJsonMissingPath('data.nodes.user');
    }

    public function test_the_profile_carries_no_nodes_when_no_menu_is_listed(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJsonPath('data.nodes', []);
    }

    public function test_the_profile_never_exposes_the_password(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJsonMissingPath('data.profile.password');
    }

    public function test_logout_invalidates_the_token(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('admin/auth/logout')
            ->assertStatus(200);

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_a_captcha_token_is_burned_even_when_the_answer_is_wrong(): void {
        $this->user();

        $this->login(code: '00000')->assertJson(['error' => 'invalid-captcha']);

        $response = $this->postJson('admin/auth/login', [
            'username' => 'alice',
            'password' => self::PASSWORD,
            'token' => 'captcha-token',
            'code' => self::CODE
        ]);

        $response->assertJson(['success' => false, 'error' => 'invalid-captcha']);
    }

    public function test_a_wrong_password_is_logged_after_the_rollback(): void {
        $user = $this->user();

        $this->login(password: 'wrong-Passw0rd')->assertJson(['code' => 422, 'error' => 'invalid-username-or-password']);

        $this->assertSame(1, UserLog::query()->where('user_id', $user->id)->where('type', UserLogType::LoginFailed)->count());
    }

    public function test_an_unknown_username_writes_no_log_and_answers_identically(): void {
        $this->user();

        $response = $this->login(username: 'nobody');

        $response->assertJson(['code' => 422, 'error' => 'invalid-username-or-password']);
        $this->assertSame(0, UserLog::query()->count());
    }

    public function test_a_user_that_was_never_activated_cannot_log_in(): void {
        $this->user(['enable_time' => null]);

        $this->login()->assertJson(['error' => 'invalid-username-or-password']);
    }

    public function test_a_user_whose_activation_is_still_in_the_future_cannot_log_in(): void {
        $this->user(['enable_time' => now()->addDay()]);

        $this->login()->assertJson(['error' => 'invalid-username-or-password']);
    }

    public function test_a_disabled_user_cannot_log_in(): void {
        $this->user(['disabled' => true]);

        $this->login()->assertJson(['error' => 'invalid-username-or-password']);
    }

    public function test_a_user_past_its_disable_time_cannot_log_in(): void {
        $this->user(['disable_time' => now()->subDay()]);

        $this->login()->assertJson(['error' => 'invalid-username-or-password']);
    }

    public function test_a_user_without_a_password_cannot_log_in(): void {
        $this->user(['password' => null]);

        $this->login()->assertJson(['error' => 'invalid-username-or-password']);
        $this->assertSame(0, UserLog::query()->where('type', UserLogType::Login)->count());
    }

    public function test_every_authentication_failure_answers_with_http_200(): void {
        $this->user();

        $this->login(code: '00000')->assertStatus(200);
        $this->login(password: 'wrong-Passw0rd')->assertStatus(200);
        $this->withToken('nonsense')
            ->postJson('admin/auth/profile')
            ->assertStatus(200);
    }

    public function test_repeated_failures_are_rate_limited(): void {
        $this->user();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login(password: 'wrong-Passw0rd');
        }

        $response = $this->login(password: 'wrong-Passw0rd');

        $response->assertStatus(200);
        $response->assertJson(['code' => 429, 'error' => 'too-many-requests']);
    }

    public function test_changing_the_password_requires_the_current_one(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/passwd', ['current' => 'wrong-Passw0rd', 'password' => 'another-Passw0rd']);

        $response->assertJson(['code' => 422, 'error' => 'invalid-password']);
    }

    public function test_a_weak_new_password_is_rejected(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/passwd', ['current' => self::PASSWORD, 'password' => 'short']);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
    }

    public function test_a_changed_password_replaces_the_old_one(): void {
        $user = $this->user();

        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('admin/auth/passwd', ['current' => self::PASSWORD, 'password' => 'another-Passw0rd'])
            ->assertJsonPath('success', true);

        $this->login(password: self::PASSWORD)->assertJson(['error' => 'invalid-username-or-password']);
        $this->login(password: 'another-Passw0rd')->assertJsonPath('success', true);
        $this->assertSame(1, UserLog::query()->where('user_id', $user->id)->where('type', UserLogType::ChangePassword)->count());
    }

}
