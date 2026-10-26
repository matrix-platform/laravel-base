<?php //>

namespace Tests\Feature\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use MatrixPlatform\Models\IdentityType;
use Tests\FeatureTestCase;

class LoginThrottleMiddlewareTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        foreach (IdentityType::cases() as $identity) {
            $bundle = $identity->bundle();

            $router->middleware("login-throttle-api:{$bundle}")->post("probe/{$bundle}", fn () => ['ok' => true]);
        }

        $router->middleware('login-throttle-api:admin')->post('probe/success', fn () => ['success' => true]);
        $router->middleware('login-throttle-api:admin')->post('probe/failure', fn () => response()->json(['success' => false, 'code' => 422, 'error' => 'invalid-captcha']));
        $router->middleware('login-throttle-api:admin')->post('probe/in-flight', fn (Request $request) => ['attempts' => RateLimiter::attempts("matrix-login-admin|{$request->ip()}|")]);
    }

    public function test_each_bundle_keeps_its_own_bucket(): void {
        $max = intval(cfg('admin.login-throttle-max'));

        for ($attempt = 0; $attempt < $max; $attempt++) {
            $this->postJson('probe/admin');
        }

        $this->postJson('probe/admin')->assertStatus(429);
        $this->postJson('probe/member')->assertStatus(200);
        $this->postJson('probe/vendor')->assertStatus(200);
    }

    public function test_the_bucket_is_keyed_by_address_and_username(): void {
        $max = intval(cfg('admin.login-throttle-max'));

        for ($attempt = 0; $attempt < $max; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->postJson('probe/admin', ['username' => 'alice']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('probe/admin', ['username' => 'alice'])
            ->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('probe/admin', ['username' => 'bob'])
            ->assertStatus(200);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('probe/admin', ['username' => 'alice'])
            ->assertStatus(200);
    }

    public function test_the_requests_are_throttled_once_the_limit_is_exhausted(): void {
        $max = intval(cfg('admin.login-throttle-max'));

        for ($attempt = 0; $attempt < $max; $attempt++) {
            $this->postJson('probe/admin')->assertStatus(200);
        }

        $this->postJson('probe/admin')->assertStatus(429);
    }

    public function test_a_successful_response_is_not_counted_against_the_limit(): void {
        $max = intval(cfg('admin.login-throttle-max'));

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->postJson('probe/success')->assertStatus(200);
        }
    }

    public function test_a_failed_response_is_counted_against_the_limit(): void {
        $max = intval(cfg('admin.login-throttle-max'));

        for ($attempt = 0; $attempt < $max; $attempt++) {
            $this->postJson('probe/failure')->assertStatus(200);
        }

        $this->postJson('probe/failure')->assertStatus(429);
    }

    public function test_a_request_is_counted_before_it_runs(): void {
        $this->postJson('probe/in-flight')->assertJsonPath('attempts', 1);
    }

    public function test_a_success_clears_the_failures_before_it(): void {
        $max = intval(cfg('admin.login-throttle-max'));

        for ($attempt = 0; $attempt < $max - 1; $attempt++) {
            $this->postJson('probe/failure');
        }

        $this->postJson('probe/success');

        for ($attempt = 0; $attempt < $max; $attempt++) {
            $this->postJson('probe/failure')->assertStatus(200);
        }

        $this->postJson('probe/failure')->assertStatus(429);
    }

}
