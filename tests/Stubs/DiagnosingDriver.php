<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;

/**
 * @implements Driver<MailLog>
 */
class DiagnosingDriver implements Driver {

    public function send(MessageLog $log): string {
        $log->error = 'vendor-status-6';

        error('request-failed');
    }

}
