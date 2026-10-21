<?php //>

namespace Tests\Stubs;

use Closure;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;

/**
 * @implements Driver<MailLog>
 */
class OkDriver implements Driver {

    public static ?Closure $during = null;

    public static ?int $level = null;

    public function send(MessageLog $log): string {
        self::$level = DB::transactionLevel();

        if (self::$during !== null) {
            (self::$during)($log);
        }

        return 'stub-message-id';
    }

}
