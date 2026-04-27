<?php //>

namespace Tests\Feature\Controllers;

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;
use Tests\FeatureTestCase;

class AuthControllerThrottleTest extends FeatureTestCase {

    protected function defineRoutes($router) {
        Route::prefix('auth')->controller(AuthController::class)->scan('anonymous');
    }

    private function attempt($username = 'admin') {
        return $this->postJson('/auth/login', [
            'code'     => '00000',
            'password' => 'wrong',
            'token'    => 'tok',
            'username' => $username,
        ]);
    }

    public function test_sixth_attempt_returns_429() {
        $max = cfg('admin.login-throttle-max');

        for ($i = 0; $i < $max; $i++) {
            $this->assertNotEquals(429, $this->attempt()->status());
        }

        $this->attempt()->assertStatus(429);
    }

    public function test_different_usernames_are_independent() {
        $max = cfg('admin.login-throttle-max');

        for ($i = 0; $i < $max; $i++) {
            $this->attempt('admin');
        }

        $this->attempt('admin')->assertStatus(429);
        $this->assertNotEquals(429, $this->attempt('root')->status());
    }

}
