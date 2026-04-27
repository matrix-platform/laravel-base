<?php //>

namespace Tests\Feature\Models;

use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class AuthTokenLifecycleTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);
    }

    public function test_create_token_sets_expire_time() {
        $tokenStr = User::find(User::ROOT)->createToken();
        $auth = AuthToken::where('token', $tokenStr)->first();

        $this->assertNotNull($auth->expire_time);
        $this->assertEqualsWithDelta(
            now()->addDays(cfg('admin.token-ttl-days'))->timestamp,
            $auth->expire_time->timestamp,
            1
        );
    }

}
