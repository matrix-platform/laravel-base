<?php //>

namespace Tests\Unit;

use MatrixPlatform\BaseServiceProvider;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase {

    public function test_service_provider_is_autoloadable(): void {
        $this->assertTrue(class_exists(BaseServiceProvider::class));
    }

}
