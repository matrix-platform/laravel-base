<?php //>

namespace Tests\Feature\Http\Controllers;

use Illuminate\Routing\Router;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\FeatureTestCase;
use Tests\Stubs\StubController;
use Tests\Stubs\Widget;

class BaseControllerTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware('envelope-api')->group(fn () => ActionRoutes::scan(StubController::class));
    }

    public function test_a_returned_value_is_wrapped_in_the_success_envelope(): void {
        $response = $this->postJson('plain');

        $response->assertStatus(200);
        $response->assertExactJson(['success' => true, 'data' => 'plain']);
    }

    public function test_a_returned_response_is_sent_untouched(): void {
        $response = $this->postJson('raw');

        $response->assertStatus(200);
        $response->assertExactJson(['raw' => true]);
    }

    public function test_the_action_runs_inside_a_transaction(): void {
        $this->postJson('rollback');

        $this->assertSame(0, Widget::query()->where('title', 'doomed')->count());
    }

    public function test_the_audit_log_rolls_back_with_the_action(): void {
        $this->postJson('rollback');

        $this->assertSame(0, ManipulationLog::query()->count());
    }

    public function test_rollback_callbacks_run_after_the_rollback(): void {
        cache()->forget('rollback-ran');

        $this->postJson('rollback');

        $this->assertTrue(cache()->get('rollback-ran'));
    }

}
