<?php //>

namespace Tests\Feature\Services\Admin\Shared;

use Illuminate\Routing\Router;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Services\Admin\Shared\ScheduleService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\GroupFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Widget;
use Tests\Stubs\WidgetController;

class ScheduleServiceTest extends FeatureTestCase {

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

        $this->actAsRoot();
    }

    private function service(): ScheduleService {
        return app(ScheduleService::class);
    }

    private function widget(): Widget {
        return Widget::forceCreate(['title' => 'Alpha']);
    }

    public function test_enabling_sets_the_enable_time_and_clears_the_disable_time(): void {
        $widget = $this->widget();
        $widget->disable_time = now()->subDay();
        $widget->save();

        $this->service()->toggle('widget', $widget->id, true);

        $widget->refresh();

        $this->assertNotNull($widget->enable_time);
        $this->assertNull($widget->disable_time);
    }

    public function test_disabling_sets_the_disable_time_and_leaves_the_enable_time_untouched(): void {
        $widget = $this->widget();
        $widget->enable_time = now()->subDay();
        $widget->save();

        $enabledAt = $widget->enable_time->toDateTimeString();

        $this->service()->toggle('widget', $widget->id, false);

        $widget->refresh();
        $refreshedEnableTime = $widget->enable_time;

        if ($refreshedEnableTime === null) {
            $this->fail('expected enable_time to survive the toggle');
        }

        $this->assertSame($enabledAt, $refreshedEnableTime->toDateTimeString());
        $this->assertNotNull($widget->disable_time);
    }

    public function test_the_response_reports_the_requested_state(): void {
        $widget = $this->widget();

        $result = $this->service()->toggle('widget', $widget->id, true);

        $this->assertTrue($result['enabled']);
    }

    public function test_toggling_writes_an_entry_to_the_audit_trail(): void {
        $widget = $this->widget();
        $before = ManipulationLog::query()->count();

        $this->service()->toggle('widget', $widget->id, true);

        $this->assertSame($before + 1, ManipulationLog::query()->count());
    }

    public function test_a_model_without_schedule_metadata_is_refused(): void {
        $group = GroupFactory::new()->createOne();

        $this->refusesField('prefix', 'schedule-not-supported', fn () => $this->service()->toggle('group', $group->id, true));
    }

}
