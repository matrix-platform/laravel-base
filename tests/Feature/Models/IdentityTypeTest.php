<?php //>

namespace Tests\Feature\Models;

use MatrixPlatform\Models\IdentityType;
use Tests\FeatureTestCase;

class IdentityTypeTest extends FeatureTestCase {

    public function test_every_identity_declares_an_idle_window(): void {
        foreach (IdentityType::cases() as $type) {
            $minutes = cfg("{$type->bundle()}.token-idle-minutes");

            $this->assertIsInt($minutes, "cfg/{$type->bundle()}.php must declare token-idle-minutes");
            $this->assertGreaterThan(0, $minutes, "cfg/{$type->bundle()}.php declares a useless idle window");
        }
    }

    public function test_every_identity_declares_a_login_throttle(): void {
        foreach (IdentityType::cases() as $type) {
            $bundle = $type->bundle();

            $this->assertIsInt(cfg("{$bundle}.login-throttle-window"), "cfg/{$bundle}.php must declare login-throttle-window");
            $this->assertIsInt(cfg("{$bundle}.login-throttle-max"), "cfg/{$bundle}.php must declare login-throttle-max");
        }
    }

    public function test_every_identity_has_its_own_cookie_name(): void {
        $cookies = array_map(fn (IdentityType $type): string => $type->cookie(), IdentityType::cases());

        $this->assertSame($cookies, array_unique($cookies));
    }

}
