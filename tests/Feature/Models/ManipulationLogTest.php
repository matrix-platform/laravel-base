<?php //>

namespace Tests\Feature\Models;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;
use MatrixPlatform\Models\ManipulationLog;
use Tests\FeatureTestCase;
use Tests\Stubs\Widget;

/**
 * Manipulation logs are append-only at the model level: saving an existing row or deleting one throws LogicException.
 * Bulk queries and raw SQL bypass model events and are not restricted.
 */
class ManipulationLogTest extends FeatureTestCase {

    private ManipulationLog $log;

    protected function setUp(): void {
        parent::setUp();

        Widget::forceCreate(['title' => 'alpha']);

        $this->log = ManipulationLog::query()->orderByDesc('id')->firstOrFail();
    }

    /**
     * @param Closure(): mixed $write
     */
    private function assertRejected(Closure $write): void {
        try {
            DB::transaction($write);
        } catch (LogicException) {
            $this->assertSame(1, ManipulationLog::query()->whereKey($this->log->id)->where('data_type', 'stub_widget')->count());

            return;
        }

        $this->fail('the write was not rejected');
    }

    public function test_the_model_cannot_be_updated(): void {
        $this->assertRejected(function (): void {
            $this->log->data_type = 'tampered';
            $this->log->save();
        });
    }

    public function test_the_model_cannot_be_deleted(): void {
        $this->assertRejected(fn () => $this->log->delete());
    }

    public function test_new_logs_can_still_be_written(): void {
        Widget::forceCreate(['title' => 'beta']);

        $this->assertSame(2, ManipulationLog::query()->where('data_type', 'stub_widget')->count());
    }

}
