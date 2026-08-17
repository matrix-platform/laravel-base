<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;
use RuntimeException;

/**
 * @implements Driver<MailLog>
 */
class FailDriver implements Driver {

    public function send(MessageLog $log): string {
        throw new RuntimeException('smtp exploded');
    }

}
