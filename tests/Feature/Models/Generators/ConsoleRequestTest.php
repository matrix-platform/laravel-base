<?php //>

namespace Tests\Feature\Models\Generators;

use Illuminate\Routing\Router;
use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorEndpoint;
use MatrixPlatform\Models\Generators\CreatorUserAgent;
use MatrixPlatform\Models\ManipulationLog;
use Tests\FeatureTestCase;
use Tests\Stubs\Widget;

class ConsoleRequestTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->post('generator-probe', fn () => response()->json(['id' => Widget::forceCreate(['title' => 'probe'])->id]));
    }

    private function log(int $id): ManipulationLog {
        return ManipulationLog::query()
            ->where('data_type', 'stub_widget')
            ->where('data_id', $id)
            ->firstOrFail();
    }

    public function test_the_request_the_console_boots_with_yields_no_origin(): void {
        $widget = new Widget();

        $this->assertNull((new CreatorAddress())->generate(null, $widget));
        $this->assertNull((new CreatorEndpoint())->generate(null, $widget));
        $this->assertNull((new CreatorUserAgent())->generate(null, $widget));
    }

    public function test_a_write_outside_any_http_request_records_no_origin(): void {
        $widget = Widget::forceCreate(['title' => 'seeded']);
        $log = $this->log($widget->id);

        $this->assertNull($widget->ip);
        $this->assertNull($log->endpoint);
        $this->assertNull($log->ip);
    }

    public function test_a_write_inside_an_http_request_records_its_origin(): void {
        $id = intval($this->withHeader('User-Agent', 'Probe/1.0')->postJson('generator-probe')->json('id'));

        $widget = Widget::query()->whereKey($id)->firstOrFail();
        $log = $this->log($id);

        $this->assertSame('127.0.0.1', $widget->ip);
        $this->assertSame('generator-probe', $log->endpoint);
        $this->assertSame('127.0.0.1', $log->ip);
    }

}
