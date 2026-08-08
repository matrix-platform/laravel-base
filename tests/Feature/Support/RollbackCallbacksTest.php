<?php //>

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Support\RollbackCallbacks;
use RuntimeException;
use Tests\FeatureTestCase;

class RollbackCallbacksTest extends FeatureTestCase {

    public function test_container_binding_is_scoped_to_a_single_instance(): void {
        $this->assertSame(app(RollbackCallbacks::class), app(RollbackCallbacks::class));
    }

    public function test_callbacks_run_when_a_transaction_rolls_back(): void {
        $ran = false;

        try {
            DB::transaction(function () use (&$ran): void {
                app(RollbackCallbacks::class)->register(function () use (&$ran): void {
                    $ran = true;
                });

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertTrue($ran);
    }

    public function test_callbacks_do_not_run_when_a_transaction_commits(): void {
        $ran = false;

        DB::transaction(function () use (&$ran): void {
            app(RollbackCallbacks::class)->register(function () use (&$ran): void {
                $ran = true;
            });
        });

        $this->assertFalse($ran);
    }

}
