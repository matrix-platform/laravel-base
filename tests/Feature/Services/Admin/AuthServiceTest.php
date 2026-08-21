<?php //>

namespace Tests\Feature\Services\Admin;

use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Services\Admin\AuthService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class AuthServiceTest extends FeatureTestCase {

    public function test_logging_out_with_an_unknown_token_leaves_the_live_session_alone(): void {
        $token = UserFactory::new()->createOne()->createToken();

        app(AuthService::class)->logout('not-a-real-token');

        $this->assertNotNull(AuthToken::findByToken($token, IdentityType::User));
    }

}
