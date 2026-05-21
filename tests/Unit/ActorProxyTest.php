<?php //>

namespace Tests\Unit;

use MatrixPlatform\Support\ActorProxy;
use Tests\TestCase;

class ActorProxyTest extends TestCase {

    public function test_get_returns_null_when_no_model() {
        $proxy = new ActorProxy;
        $this->assertNull($proxy->id);
        $this->assertNull($proxy->name);
    }

    public function test_get_proxies_to_model_property() {
        $model = (object)['id' => 7, 'name' => 'Alice'];
        $proxy = new ActorProxy($model);
        $this->assertEquals(7, $proxy->id);
        $this->assertEquals('Alice', $proxy->name);
    }

    public function test_set_throws_exception() {
        $this->expectException(\Exception::class);
        $proxy = new ActorProxy;
        $proxy->id = 1;
    }

}
