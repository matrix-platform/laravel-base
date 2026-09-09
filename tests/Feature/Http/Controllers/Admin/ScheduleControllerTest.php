<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Widget;
use Tests\Stubs\WidgetController;

class ScheduleControllerTest extends FeatureTestCase {

    private string $token;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(fn () => ActionRoutes::mount('widget', WidgetController::class));
    }

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget', enable: 'enable_time', disable: 'disable_time')));

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $token, array $input): TestResponse {
        return $this->withToken($token)->postJson('admin/schedule/toggle', $input);
    }

    private function widget(): Widget {
        return Widget::forceCreate(['title' => 'Alpha']);
    }

    public function test_it_enables_the_record_and_reports_the_new_schedule(): void {
        $widget = $this->widget();

        $response = $this->send($this->token, ['prefix' => 'widget', 'id' => $widget->id, 'enabled' => true]);

        $response->assertJsonPath('data.enabled', true);
        $this->assertNotNull($widget->refresh()->enable_time);
    }

    public function test_a_missing_enabled_flag_is_a_validation_failure(): void {
        $widget = $this->widget();

        $this->send($this->token, ['prefix' => 'widget', 'id' => $widget->id])->assertJsonPath('fields.enabled', ['required']);
    }

    public function test_a_user_without_the_update_permission_is_denied(): void {
        $widget = $this->widget();
        $token = UserFactory::new()->createOne(['id' => 1001, 'permissions' => []])->createToken();

        $this->send($token, ['prefix' => 'widget', 'id' => $widget->id, 'enabled' => true])
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
