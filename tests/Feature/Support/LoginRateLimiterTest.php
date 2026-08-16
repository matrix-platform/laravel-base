<?php //>

namespace Tests\Feature\Support;

use Illuminate\Http\Request;
use MatrixPlatform\Support\LoginRateLimiter;
use Tests\FeatureTestCase;

class LoginRateLimiterTest extends FeatureTestCase {

    private function request(): Request {
        return Request::create('/', 'POST', ['username' => 'zoe']);
    }

    public function test_the_bundle_decides_which_settings_are_read(): void {
        $configured = (new LoginRateLimiter('admin'))($this->request());
        $missing = (new LoginRateLimiter('nowhere'))($this->request());

        $this->assertSame(5, $configured->maxAttempts);
        $this->assertSame(0, $missing->maxAttempts);
    }

    public function test_the_bucket_is_keyed_by_address_and_username(): void {
        $limit = (new LoginRateLimiter('admin'))($this->request());

        $this->assertStringContainsString('zoe', strval($limit->key));
    }

}
