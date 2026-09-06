<?php //>

namespace MatrixPlatform\Services\Admin\Passkey;

use Webauthn\Counter\CounterChecker;
use Webauthn\CredentialRecord;
use Webauthn\Exception\CounterException;

class PasskeyCounterChecker implements CounterChecker {

    public function check(CredentialRecord $credentialRecord, int $currentCounter): void {
        if ($currentCounter === 0 && $credentialRecord->counter === 0) {
            return;
        }

        if ($currentCounter <= $credentialRecord->counter) {
            throw CounterException::create($currentCounter, $credentialRecord->counter, 'Invalid counter.');
        }
    }

}
