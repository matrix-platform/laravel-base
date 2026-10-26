<?php //>

namespace Tests\Feature\Models;

use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use Tests\FeatureTestCase;

class AuthTokenTest extends FeatureTestCase {

    public function test_the_stored_token_is_a_digest_of_the_issued_token(): void {
        $token = AuthToken::issue(IdentityType::User, 1);
        $stored = AuthToken::query()->sole()->token;

        $this->assertNotSame($token, $stored);
        $this->assertSame(hash('sha256', $token), $stored);
    }

    public function test_the_issued_token_finds_its_own_record(): void {
        $token = AuthToken::issue(IdentityType::User, 1);

        $this->assertSame(1, AuthToken::findByToken($token, IdentityType::User)?->target_id);
    }

    public function test_a_token_is_found_only_for_its_own_identity_and_never_when_empty(): void {
        $token = AuthToken::issue(IdentityType::User, 1);

        $this->assertNull(AuthToken::findByToken($token, IdentityType::Member));
        $this->assertNull(AuthToken::findByToken('', IdentityType::User));
        $this->assertNull(AuthToken::findByToken(null, IdentityType::User));
    }

}
