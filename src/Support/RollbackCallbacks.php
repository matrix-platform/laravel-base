<?php //>

namespace MatrixPlatform\Support;

use Closure;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;

class RollbackCallbacks {

    public function persist(Closure $callback, ?string $connection = null): void {
        $transaction = $this->transaction($connection);

        if ($transaction === null) {
            $callback();

            return;
        }

        $transaction->addCallback($callback);
        $transaction->addCallbackForRollback($callback);
    }

    public function register(Closure $callback, ?string $connection = null): void {
        $this->transaction($connection)?->addCallbackForRollback($callback);
    }

    private function transaction(?string $connection): ?DatabaseTransactionRecord {
        $name = $connection === null ? DB::getDefaultConnection() : $connection;

        /** @var DatabaseTransactionsManager $manager */
        $manager = app('db.transactions');

        return $manager->callbackApplicableTransactions()->last(fn (DatabaseTransactionRecord $transaction) => $transaction->connection === $name);
    }

}
