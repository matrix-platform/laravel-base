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
    }

    private function request(): Request {
        return Request::create('/', 'POST', ['username' => 'zoe']);
    }

    public function test_no_limiter_is_registered_before_a_route_is_reached(): void {
        foreach (IdentityType::cases() as $identity) {
            $this->assertNull(RateLimiter::limiter("matrix-login-{$identity->bundle()}"), $identity->bundle());
        }
    }

    public function test_reaching_a_route_registers_only_the_bundle_it_carries(): void {
        $this->postJson('probe/admin');

        $this->assertNotNull(RateLimiter::limiter('matrix-login-admin'));
        $this->assertNull(RateLimiter::limiter('matrix-login-member'));
        $this->assertNull(RateLimiter::limiter('matrix-login-vendor'));
    }

    public function test_every_identity_resolves_a_limit_from_its_own_bundle(): void {
        foreach (IdentityType::cases() as $identity) {
            $bundle = $identity->bundle();

            $this->postJson("probe/{$bundle}");

            $limiter = RateLimiter::limiter("matrix-login-{$bundle}");

            $this->assertNotNull($limiter, $bundle);
            $this->assertGreaterThan(0, $limiter($this->request())->maxAttempts);
        }
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

}
