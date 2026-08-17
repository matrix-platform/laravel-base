<?php //>

namespace MatrixPlatform\Messaging;

use MatrixPlatform\Models\MessageLog;

/**
 * @template TLog of MessageLog
 */
interface Driver {

    /**
     * @param TLog $log
     */
    public function send(MessageLog $log): string;

}
