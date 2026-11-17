<?php //>

namespace Tests\Feature\Services\Admin\Passkey;

use MatrixPlatform\Services\Admin\Passkey\PasskeyCounterChecker;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;
use Webauthn\AttestationStatement\AttestationStatement;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\TrustPath\EmptyTrustPath;

class PasskeyCounterCheckerTest extends TestCase {

    public function test_a_counter_that_stays_at_zero_on_both_sides_is_accepted(): void {
        $record = CredentialRecord::create('credential', PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, [], AttestationStatement::TYPE_NONE, EmptyTrustPath::create(), Uuid::v4(), 'public-key', '1', 0);

        $this->expectNotToPerformAssertions();

        (new PasskeyCounterChecker())->check($record, 0);
    }

}
