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
        $this->useCfg('admin', ['login-throttle-max' => 7]);

        $configured = (new LoginRateLimiter('admin'))($this->request());
        $missing = (new LoginRateLimiter('nowhere'))($this->request());

        $this->assertSame(7, $configured->maxAttempts);
        $this->assertSame(0, $missing->maxAttempts);
    }

}
