<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Services\Admin\MfaService;
use PragmaRX\Google2FA\Google2FA;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class AuthControllerTest extends FeatureTestCase {

    private const CODE = '13579';
    private const PASSWORD = 'secret-Passw0rd';

    private function comparisons(callable $callback, ?bool $forcedResult = null): int {
        $inner = app(Hasher::class);

        $spy = new class($inner, $forcedResult) implements Hasher {

            public int $checks = 0;

            public function __construct(private Hasher $inner, private ?bool $forcedResult) {}

            /**
             * @param array<string, mixed> $options
             */
            public function check($value, $hashedValue, array $options = []): bool {
                $this->checks++;

                return $this->forcedResult === null ? $this->inner->check($value, $hashedValue, $options) : $this->forcedResult;
            }

            /**
             * @return array<string, mixed>
             */
            public function info($hashedValue): array {
                return $this->inner->info($hashedValue);
            }

            /**
             * @param array<string, mixed> $options
             */
            public function make($value, array $options = []): string {
                return $this->inner->make($value, $options);
            }

            /**
             * @param array<string, mixed> $options
             */
            public function needsRehash($hashedValue, array $options = []): bool {
                return $this->inner->needsRehash($hashedValue, $options);
            }

        };

        Hash::swap($spy);

        $callback();

        Hash::swap($inner);

        return $spy->checks;
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function login(string $username = 'alice', string $password = self::PASSWORD, string $code = self::CODE): TestResponse {
        return $this->postJson('admin/auth/login', [
            'username' => $username,
            'password' => $password,
            'token' => $this->captcha(self::CODE),
            'code' => $code
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function user(array $attributes = []): User {
        return UserFactory::new()->createOne(array_merge(['username' => 'alice', 'password' => self::PASSWORD], $attributes));
    }

    private function enableMfa(User $user): string {
        $setup = app(MfaService::class)->setup($user);
        app(MfaService::class)->confirm($user, (new Google2FA())->getCurrentOtp($setup['secret']));

        return $setup['secret'];
    }

    /**
     * @return array{0: User, 1: string, 2: string}
     */
    private function challenged(): array {
        $user = $this->user();
        $secret = $this->enableMfa($user);
        $challenge = strval($this->login()->json('data.challenge'));

        return [$user, $challenge, (new Google2FA())->getCurrentOtp($secret)];
    }

    /**
     * @param TestResponse<JsonResponse> $response
     */
    private function trustCookie(TestResponse $response): ?string {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'matrix-mfa-trust') {
                return $cookie->getValue();
            }
        }

        return null;
    }

    public function test_the_captcha_endpoint_returns_a_token_and_a_rendered_image(): void {
        $prefix = 'data:image/png;base64,';

        $response = $this->postJson('admin/auth/captcha');

        $response->assertJsonStructure(['data' => ['token', 'image']]);

        $image = strval($response->json('data.image'));

        $this->assertNotNull(Cache::get('captcha:' . strval($response->json('data.token'))));
        $this->assertStringStartsWith($prefix, $image);
        $this->assertStringStartsWith("\x89PNG", strval(base64_decode(substr($image, strlen($prefix)), true)));
    }

    public function test_a_successful_login_returns_a_token_and_sets_the_cookie(): void {
        $this->user();

        $response = $this->login();

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['token']]);
        $response->assertCookie('matrix-user');
    }

    public function test_a_login_for_an_mfa_enabled_account_returns_a_challenge_instead_of_a_token(): void {
        $user = $this->user();

        $this->enableMfa($user);

        $response = $this->login();

        $response->assertJsonPath('data.mfa', true);
        $this->assertIsString($response->json('data.challenge'));
        $response->assertCookieMissing('matrix-user');
        $this->assertSame(0, UserLog::query()->where('type', UserLogType::Login)->count());
    }

    public function test_the_mfa_endpoint_with_the_correct_code_returns_a_token_sets_the_cookie_and_logs_in(): void {
        [$user, $challenge, $code] = $this->challenged();

        $response = $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => $code]);

        $response->assertJsonStructure(['data' => ['token']]);
        $response->assertCookie('matrix-user');
        $this->assertSame(1, UserLog::query()->where('user_id', $user->id)->where('type', UserLogType::Login)->count());
    }

    public function test_the_mfa_endpoint_accepts_the_challenge_even_when_the_cache_returns_the_user_id_as_a_string(): void {
        [$user, $challenge, $code] = $this->challenged();

        Cache::put("mfa-challenge:{$challenge}", strval($user->id), 60);

        $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => $code])
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_the_mfa_endpoint_with_remember_issues_a_trust_cookie_that_skips_a_later_mfa_challenge(): void {
        [, $challenge, $code] = $this->challenged();

        $response = $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => $code, 'remember' => true]);
        $trust = $this->trustCookie($response);

        $this->assertIsString($trust);
        $response->assertJsonMissingPath('data.trust');

        $this->withCredentials()->withUnencryptedCookie('matrix-mfa-trust', strval($trust));

        $this->login()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_the_mfa_endpoint_without_remember_does_not_issue_a_trust_cookie(): void {
        [, $challenge, $code] = $this->challenged();

        $response = $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => $code]);

        $this->assertNull($this->trustCookie($response));
    }

    public function test_a_trust_cookie_does_not_skip_the_mfa_challenge_for_a_different_account(): void {
        [, $challenge, $code] = $this->challenged();

        $trust = $this->trustCookie($this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => $code, 'remember' => true]));

        $this->enableMfa($this->user(['username' => 'bob']));

        $this->withCredentials()->withUnencryptedCookie('matrix-mfa-trust', strval($trust));

        $this->login('bob')->assertJsonPath('data.mfa', true);
    }

    public function test_the_mfa_endpoint_with_the_wrong_code_reports_invalid_code_and_logs_the_failure(): void {
        $user = $this->user();

        $this->enableMfa($user);

        $challenge = strval($this->login()->json('data.challenge'));
        $response = $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => '000000']);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.code', ['invalid-code']);
        $this->assertSame(1, UserLog::query()->where('user_id', $user->id)->where('type', UserLogType::MfaChallengeFailed)->count());
    }

    public function test_repeated_mfa_failures_are_rate_limited(): void {
        $user = $this->user();

        $this->enableMfa($user);

        $challenge = strval($this->login()->json('data.challenge'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => '000000']);
        }

        $response = $this->postJson('admin/auth/mfa', ['username' => 'alice', 'challenge' => $challenge, 'code' => '000000']);

        $response->assertStatus(200);
        $response->assertJson(['code' => 429, 'error' => 'too-many-requests']);
    }

    public function test_the_full_self_service_mfa_setup_confirm_and_disable_cycle(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $secret = strval($this->withToken($token)->postJson('admin/auth/mfa/setup')->json('data.secret'));
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->withToken($token)
            ->postJson('admin/auth/mfa/confirm', ['code' => $code])
            ->assertJsonPath('success', true);

        $this->login()->assertJsonPath('data.mfa', true);

        $this->withToken($token)
            ->postJson('admin/auth/mfa/disable', ['password' => self::PASSWORD])
            ->assertJsonPath('success', true);

        $this->login()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_mfa_fields_cannot_be_written_through_the_generic_user_update_endpoint(): void {
        $user = $this->user();
        $secret = $this->enableMfa($user);

        $admin = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();

        $this->withToken($admin)
            ->postJson("admin/user/{$user->id}/update", [
                'username' => 'alice',
                'password' => null,
                'group_id' => null,
                'disabled' => false,
                'enable_time' => null,
                'disable_time' => null,
                'permissions' => null,
                'secret' => 'malicious'
            ])
            ->assertJsonPath('success', true);

        $fresh = User::query()->findOrFail($user->id);

        $this->assertSame($secret, $fresh->secret);
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

    public function test_the_profile_carries_the_production_nodes_without_any_configuration(): void {
        $this->user(['id' => User::ROOT]);

        $token = $this->login()->json('data.token');

        $nodes = $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->json('data.nodes');

        $this->assertIsArray($nodes);
        $this->assertNotEmpty($nodes);

        foreach ($nodes as $node) {
            $this->assertIsArray($node);
            $this->assertIsString($node['title']);
            $this->assertNotSame('', $node['title']);
        }
    }

    public function test_the_profile_never_exposes_the_permissions(): void {
        $this->user(['id' => User::ROOT, 'permissions' => ['user' => ['query' => true]]]);

        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJsonMissingPath('data.profile.permissions');
    }

    public function test_the_profile_carries_no_nodes_when_no_menu_is_listed(): void {
        $this->useMenus(null);

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
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile', null);
    }

    public function test_the_profile_endpoint_returns_an_empty_result_without_a_token(): void {
        $this->postJson('admin/auth/profile')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile', null)
            ->assertJsonPath('data.nodes', []);
    }

    public function test_a_captcha_token_is_burned_even_when_the_answer_is_wrong(): void {
        $this->user();

        $this->login(code: '00000')->assertJson(['error' => 'validation-failed']);

        $response = $this->postJson('admin/auth/login', [
            'username' => 'alice',
            'password' => self::PASSWORD,
            'token' => 'captcha-token',
            'code' => self::CODE
        ]);

        $response->assertJson(['success' => false, 'error' => 'validation-failed']);
    }

    public function test_a_wrong_password_is_logged_after_the_rollback(): void {
        $user = $this->user();

        $this->login(password: 'wrong-Passw0rd')->assertJson(['code' => 422, 'error' => 'validation-failed']);

        $this->assertSame(1, UserLog::query()->where('user_id', $user->id)->where('type', UserLogType::LoginFailed)->count());
    }

    public function test_an_unknown_username_writes_no_log_and_answers_identically(): void {
        $this->user();

        $response = $this->login(username: 'nobody');

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $this->assertSame(0, UserLog::query()->count());
    }

    public function test_a_user_that_was_never_activated_cannot_log_in(): void {
        $this->user(['enable_time' => null]);

        $this->login()->assertJson(['error' => 'validation-failed']);
    }

    public function test_a_user_whose_activation_is_still_in_the_future_cannot_log_in(): void {
        $this->user(['enable_time' => now()->addDay()]);

        $this->login()->assertJson(['error' => 'validation-failed']);
    }

    public function test_a_disabled_user_cannot_log_in(): void {
        $this->user(['disabled' => true]);

        $this->login()->assertJson(['error' => 'validation-failed']);
    }

    public function test_a_user_past_its_disable_time_cannot_log_in(): void {
        $this->user(['disable_time' => now()->subDay()]);

        $this->login()->assertJson(['error' => 'validation-failed']);
    }

    public function test_a_user_without_a_password_cannot_log_in(): void {
        $this->user(['password' => null]);

        $this->login()->assertJson(['error' => 'validation-failed']);
        $this->assertSame(0, UserLog::query()->where('type', UserLogType::Login)->count());
    }

    public function test_a_user_without_a_password_hash_cannot_log_in_when_the_dummy_hash_matches(): void {
        $user = $this->user(['password' => null]);

        foreach ([null, ''] as $hash) {
            $user
                ->getConnection()
                ->table($user->getTable())
                ->where('id', $user->id)
                ->update(['password' => $hash]);
            $response = null;

            $comparisons = $this->comparisons(function () use (&$response): void {
                $response = $this->login();
            }, true);

            $this->assertSame(1, $comparisons);
            $this->assertInstanceOf(TestResponse::class, $response);
            $response->assertJson(['error' => 'validation-failed']);
        }

        $this->assertSame(0, UserLog::query()->where('type', UserLogType::Login)->count());
    }

    public function test_every_failed_login_runs_exactly_one_password_comparison(): void {
        $this->user();
        $this->user(['username' => 'bob', 'password' => null]);

        $this->assertSame(1, $this->comparisons(fn () => $this->login(username: 'nobody')));
        $this->assertSame(1, $this->comparisons(fn () => $this->login(username: 'bob')));
        $this->assertSame(1, $this->comparisons(fn () => $this->login(password: 'wrong-Passw0rd')));
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

    public function test_repeated_successes_are_not_rate_limited(): void {
        $this->user();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login()->assertJsonPath('success', true);
        }

        $response = $this->login();

        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['token']]);
    }

    public function test_changing_the_password_requires_the_current_one(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/passwd', ['current' => 'wrong-Passw0rd', 'password' => 'another-Passw0rd']);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
    }

    public function test_changing_the_password_requires_the_current_field_to_be_sent(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/passwd', ['password' => 'another-Passw0rd']);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.current', ['required']);
    }

    public function test_a_weak_new_password_is_rejected(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/passwd', ['current' => self::PASSWORD, 'password' => 'short']);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void {
        $this->user();

        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->postJson('admin/auth/passwd', ['current' => self::PASSWORD, 'password' => self::PASSWORD]);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.password', ['different']);
    }

    public function test_a_changed_password_replaces_the_old_one(): void {
        $user = $this->user();

        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('admin/auth/passwd', ['current' => self::PASSWORD, 'password' => 'another-Passw0rd'])
            ->assertJsonPath('success', true);

        $this->login(password: self::PASSWORD)->assertJson(['error' => 'validation-failed']);
        $this->login(password: 'another-Passw0rd')->assertJsonPath('success', true);
        $this->assertSame(1, UserLog::query()->where('user_id', $user->id)->where('type', UserLogType::ChangePassword)->count());
    }

    public function test_changing_the_password_ends_the_other_sessions_but_not_this_one(): void {
        $user = $this->user();

        $abandoned = strval($this->login()->json('data.token'));
        $current = strval($this->login()->json('data.token'));

        $this->withToken($current)
            ->postJson('admin/auth/passwd', ['current' => self::PASSWORD, 'password' => 'another-Passw0rd'])
            ->assertJsonPath('success', true);

        $this->withToken($current)
            ->postJson('admin/auth/profile')
            ->assertJsonPath('success', true);

        $this->withToken($abandoned)
            ->postJson('admin/auth/profile')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile', null);

        $this->assertSame(1, AuthToken::query()->where('target_id', $user->id)->count());
    }

}
