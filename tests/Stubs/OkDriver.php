<?php //>

namespace Tests\Stubs;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;

/**
 * @implements Driver<MailLog>
 */
class OkDriver implements Driver {

    public static ?int $level = null;

    public function send(MessageLog $log): string {
        self::$level = DB::transactionLevel();

        return 'stub-message-id';
    }

}
