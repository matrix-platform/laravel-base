<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;

/**
 * @implements Driver<MailLog>
 */
class RefusingDriver implements Driver {

    public function send(MessageLog $log): string {
        error('invalid-message-receiver');
    }

}
