<?php //>

namespace Tests\Feature\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class BaseControllerTest extends FeatureTestCase {

    protected function defineRoutes($router) {
        $router->post('/test/success', [TestController::class, 'successAction']);
        $router->post('/test/service-error', [TestController::class, 'serviceErrorAction']);
        $router->post('/test/validation-error', [TestController::class, 'validationErrorAction']);
        $router->post('/test/not-found', [TestController::class, 'notFoundAction']);
        $router->post('/test/response', [TestController::class, 'responseAction']);
        $router->post('/test/transaction', [TestController::class, 'transactionAction']);
    }

    public function test_success_returns_wrapped_data() {
        $response = $this->postJson('/test/success');
        $response->assertJson(['success' => true, 'data' => ['key' => 'value']]);
    }

    public function test_service_exception_returns_error_response() {
        $response = $this->postJson('/test/service-error');
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals(422, $data['code']);
        $this->assertEquals('test-error', $data['error']);
    }

    public function test_validation_exception_returns_422() {
        $response = $this->postJson('/test/validation-error');
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals(422, $data['code']);
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_model_not_found_returns_404() {
        $response = $this->postJson('/test/not-found');
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals(404, $data['code']);
        $this->assertEquals('data-not-found', $data['error']);
    }

    public function test_response_object_is_returned_directly() {
        $response = $this->postJson('/test/response');
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('direct'));
    }

    public function test_action_is_wrapped_in_db_transaction_and_rolled_back_on_exception() {
        try {
            $this->postJson('/test/transaction');
        } catch (\Exception $e) {
        }

        $this->assertEquals(0, User::count());
    }

}

class TestController extends BaseController {

    public function successAction() {
        return ['key' => 'value'];
    }

    public function serviceErrorAction() {
        error('test-error', 422);
    }

    public function validationErrorAction(\Illuminate\Http\Request $request) {
        $request->validate(['required_field' => ['required']]);
    }

    public function notFoundAction() {
        throw new ModelNotFoundException;
    }

    public function responseAction() {
        return response()->json(['direct' => true]);
    }

    public function transactionAction() {
        User::forceCreate(['username' => 'tx-user', 'disabled' => false]);
        throw new \RuntimeException('rollback');
    }

}
