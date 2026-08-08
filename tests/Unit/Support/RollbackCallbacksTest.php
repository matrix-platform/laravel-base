<?php //>

namespace Tests\Unit\Support;

use MatrixPlatform\Support\RollbackCallbacks;
use PHPUnit\Framework\TestCase;

class RollbackCallbacksTest extends TestCase {

    public function test_run_executes_registered_callbacks_in_order(): void {
        $calls = [];
        $callbacks = new RollbackCallbacks();

        $callbacks->register(function () use (&$calls): void {
            $calls[] = 'first';
        });

        $callbacks->register(function () use (&$calls): void {
            $calls[] = 'second';
        });

        $callbacks->run();

        $this->assertSame(['first', 'second'], $calls);
    }

    public function test_run_clears_the_queue_so_callbacks_fire_once(): void {
        $calls = 0;
        $callbacks = new RollbackCallbacks();

        $callbacks->register(function () use (&$calls): void {
            $calls++;
        });

        $callbacks->run();
        $callbacks->run();

        $this->assertSame(1, $calls);
    }

    public function test_run_on_empty_queue_leaves_the_object_usable(): void {
        $calls = 0;
        $callbacks = new RollbackCallbacks();

        $callbacks->run();

        $callbacks->register(function () use (&$calls): void {
            $calls++;
        });

        $callbacks->run();

        $this->assertSame(1, $calls);
    }

    public function test_callback_registered_during_run_waits_for_the_next_run(): void {
        $calls = [];
        $callbacks = new RollbackCallbacks();

        $callbacks->register(function () use (&$calls, $callbacks): void {
            $calls[] = 'outer';

            $callbacks->register(function () use (&$calls): void {
                $calls[] = 'inner';
            });
        });

        $callbacks->run();

        $this->assertSame(['outer'], $calls);

        $callbacks->run();

        $this->assertSame(['outer', 'inner'], $calls);
    }

}
