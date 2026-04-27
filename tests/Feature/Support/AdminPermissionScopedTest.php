<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminPermission;
use Tests\TestCase;

class AdminPermissionScopedTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $fakeUser = (object) ['group_id' => null, 'id' => User::ROOT];

        $this->app['request']->setUserResolver(fn () => $fakeUser);
    }

    public function test_same_instance_within_request() {
        $p1 = app(AdminPermission::class);
        $p2 = app(AdminPermission::class);

        $this->assertSame($p1, $p2);
    }

    public function test_fresh_instance_across_100_simulated_requests() {
        $previous = app(AdminPermission::class);

        for ($i = 0; $i < 100; $i++) {
            app()->forgetScopedInstances();

            $current = app(AdminPermission::class);

            $this->assertNotSame($previous, $current, "Request {$i}: 應為全新實例");

            $previous = $current;
        }
    }

}
