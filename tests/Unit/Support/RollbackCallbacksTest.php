<?php //>

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Support\RollbackCallbacks;
use Tests\TestCase;

class RollbackCallbacksTest extends TestCase {

    public function test_outside_a_transaction_a_persisted_callback_runs_at_once(): void {
        $calls = 0;

        $this->assertSame(0, DB::transactionLevel());

        (new RollbackCallbacks())->persist(function () use (&$calls): void {
            $calls++;
        });

        $this->assertSame(1, $calls);
    }

    public function test_outside_a_transaction_a_registered_callback_never_runs(): void {
        $ran = false;

        $this->assertSame(0, DB::transactionLevel());

        (new RollbackCallbacks())->register(function () use (&$ran): void {
            $ran = true;
        });

        $this->assertFalse($ran);
    }

}
