<?php //>

namespace Tests\Unit;

use MatrixPlatform\Support\Actor;
use MatrixPlatform\Support\ActorProxy;
use Tests\TestCase;

class ActorTest extends TestCase {

    public function test_user_returns_guest_proxy_when_not_set() {
        $actor = new Actor;
        $this->assertInstanceOf(ActorProxy::class, $actor->user);
        $this->assertNull($actor->user->id);
    }

    public function test_member_returns_guest_proxy_when_not_set() {
        $actor = new Actor;
        $this->assertInstanceOf(ActorProxy::class, $actor->member);
        $this->assertNull($actor->member->id);
    }

    public function test_user_can_be_set_once() {
        $actor = new Actor;
        $model = (object)['id' => 42];
        $actor->user = $model;
        $this->assertEquals(42, $actor->user->id);
    }

    public function test_member_can_be_set_once() {
        $actor = new Actor;
        $model = (object)['id' => 99];
        $actor->member = $model;
        $this->assertEquals(99, $actor->member->id);
    }

    public function test_setting_user_twice_throws_exception() {
        $this->expectException(\Exception::class);
        $actor = new Actor;
        $actor->user = (object)['id' => 1];
        $actor->user = (object)['id' => 2];
    }

    public function test_setting_member_twice_throws_exception() {
        $this->expectException(\Exception::class);
        $actor = new Actor;
        $actor->member = (object)['id' => 1];
        $actor->member = (object)['id' => 2];
    }

    public function test_unsupported_property_throws_exception() {
        $this->expectException(\Exception::class);
        $actor = new Actor;
        $_ = $actor->unknown;
    }

}
