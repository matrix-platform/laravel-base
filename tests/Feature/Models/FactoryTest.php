<?php //>

namespace Tests\Feature\Models;

use MatrixPlatform\Models\User;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class FactoryTest extends FeatureTestCase {

    public function test_the_factory_persists_a_row(): void {
        $user = UserFactory::new()->createOne();

        $this->assertSame($user->id, User::query()->whereKey($user->id)->firstOrFail()->id);
    }

    public function test_the_defaults_produce_a_user_that_can_authenticate(): void {
        $user = UserFactory::new()->createOne();

        $this->assertFalse($user->disabled);
        $this->assertNotNull($user->enable_time);
        $this->assertSame($user->id, User::query()->whereEnabled()->firstOrFail()->id);
    }

}
