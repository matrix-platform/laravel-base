<?php //>

namespace Tests\Feature\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Support\RollbackCallbacks;
use RuntimeException;
use Tests\FeatureTestCase;

class RollbackCallbacksTest extends FeatureTestCase {

    private function otherConnection(): Connection {
        config()->set('database.connections.other', config('database.connections.' . DB::getDefaultConnection()));

        $this->beforeApplicationDestroyed(fn () => DB::purge('other'));

        return DB::connection('other');
    }

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

    public function test_a_caught_inner_rollback_leaves_the_outer_callbacks_alone(): void {
        $outer = false;
        $inner = false;

        DB::transaction(function () use (&$outer, &$inner): void {
            app(RollbackCallbacks::class)->register(function () use (&$outer): void {
                $outer = true;
            });

            try {
                DB::transaction(function () use (&$inner): void {
                    app(RollbackCallbacks::class)->register(function () use (&$inner): void {
                        $inner = true;
                    });

                    throw new RuntimeException('force inner rollback');
                });
            } catch (RuntimeException) {
            }
        });

        $this->assertFalse($outer);
        $this->assertTrue($inner);
    }

    public function test_an_outer_rollback_runs_the_callbacks_of_a_committed_inner_transaction(): void {
        $ran = false;

        try {
            DB::transaction(function () use (&$ran): void {
                DB::transaction(function () use (&$ran): void {
                    app(RollbackCallbacks::class)->register(function () use (&$ran): void {
                        $ran = true;
                    });
                });

                throw new RuntimeException('force outer rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertTrue($ran);
    }

    public function test_a_callback_registered_outside_a_transaction_is_not_run_by_a_later_rollback(): void {
        $ran = false;

        app(RollbackCallbacks::class)->register(function () use (&$ran): void {
            $ran = true;
        });

        try {
            DB::transaction(fn () => throw new RuntimeException('force rollback'));
        } catch (RuntimeException) {
        }

        $this->assertFalse($ran);
    }

    public function test_a_persisted_callback_runs_once_when_the_transaction_rolls_back(): void {
        $calls = 0;

        try {
            DB::transaction(function () use (&$calls): void {
                app(RollbackCallbacks::class)->persist(function () use (&$calls): void {
                    $calls++;
                });

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(1, $calls);
    }

    public function test_a_persisted_callback_runs_once_when_the_transaction_commits(): void {
        $calls = 0;

        DB::transaction(function () use (&$calls): void {
            app(RollbackCallbacks::class)->persist(function () use (&$calls): void {
                $calls++;
            });
        });

        $this->assertSame(1, $calls);
    }

    public function test_a_persisted_callback_runs_once_across_a_caught_inner_rollback_and_an_outer_commit(): void {
        $calls = 0;

        DB::transaction(function () use (&$calls): void {
            try {
                DB::transaction(function () use (&$calls): void {
                    app(RollbackCallbacks::class)->persist(function () use (&$calls): void {
                        $calls++;
                    });

                    throw new RuntimeException('force inner rollback');
                });
            } catch (RuntimeException) {
            }
        });

        $this->assertSame(1, $calls);
    }

    public function test_a_callback_stays_with_its_own_connection_while_another_connection_is_transacting(): void {
        $other = $this->otherConnection();
        $ran = 0;
        $afterOther = null;

        try {
            DB::transaction(function () use ($other, &$ran, &$afterOther): void {
                $other->beginTransaction();

                app(RollbackCallbacks::class)->register(function () use (&$ran): void {
                    $ran++;
                });

                $other->rollBack();

                $afterOther = $ran;

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $afterOther);
        $this->assertSame(1, $ran);
    }

    public function test_a_callback_can_be_bound_to_a_named_connection(): void {
        $other = $this->otherConnection();
        $ran = 0;

        DB::transaction(function () use ($other, &$ran): void {
            $other->beginTransaction();

            app(RollbackCallbacks::class)->register(function () use (&$ran): void {
                $ran++;
            }, 'other');

            $other->rollBack();
        });

        $this->assertSame(1, $ran);
    }

    public function test_a_persisted_callback_waits_for_its_own_connection_to_commit(): void {
        $other = $this->otherConnection();
        $calls = 0;

        DB::transaction(function () use ($other, &$calls): void {
            $other->beginTransaction();

            app(RollbackCallbacks::class)->persist(function () use (&$calls): void {
                $calls++;
            });

            $other->commit();

            $this->assertSame(0, $calls);
        });

        $this->assertSame(1, $calls);
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
