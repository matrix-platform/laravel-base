<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Support\Actor;
use Tests\TestCase;

class ActorOctaneTest extends TestCase {

    public function test_same_instance_within_request() {
        $a1 = app(Actor::class);
        $a2 = app(Actor::class);

        $this->assertSame($a1, $a2);
    }

    public function test_fresh_instance_across_100_simulated_requests() {
        for ($i = 0; $i < 100; $i++) {
            app()->forgetScopedInstances();

            $actor = app(Actor::class);

            $this->assertNull($actor->user->id, "Request {$i}: actor->user->id 應為 null");
        }
    }

}
