<?php //>

namespace Tests\Feature\Controllers;

use Illuminate\Support\Facades\Cache;
use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Http\Controllers\Admin\AuthController;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class AuthControllerTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);
    }

    protected function defineRoutes($router) {
        $router->post('/auth/captcha', [AuthController::class, 'captcha']);
        $router->post('/auth/login', [AuthController::class, 'login']);
    }

    public function test_captcha_returns_token_and_image() {
        $response = $this->postJson('/auth/captcha');
        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('token', $response->json('data'));
        $this->assertStringStartsWith('data:image/png;base64,', $response->json('data.image'));
    }

    public function test_login_validation_failure_returns_422() {
        $response = $this->postJson('/auth/login', []);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals(422, $data['code']);
    }

    public function test_login_success_sets_cookie() {
        $user = User::find(User::ADMIN);
        $user->password = 'correct';
        $user->save();

        $captchaData = $this->postJson('/auth/captcha')->json('data');
        $captchaToken = $captchaData['token'];
        $code = 'ABCDE';
        Cache::put("captcha:{$captchaToken}", hash('sha256', $code), cfg('admin.captcha-ttl'));

        $response = $this->postJson('/auth/login', [
            'username' => 'admin',
            'password' => 'correct',
            'token' => $captchaToken,
            'code' => $code
        ]);

        $response->assertJson(['success' => true]);
        $response->assertCookie('matrix-user');
    }

}
