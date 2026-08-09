<?php //>

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Actor;
use Tests\FeatureTestCase;

class HelpersTest extends FeatureTestCase {

    private function model(): Model {
        return new class extends Model {};
    }

    public function test_actor_returns_the_same_instance_within_a_request(): void {
        $this->assertSame(actor(), actor());
        $this->assertSame(app(Actor::class), actor());
    }

    public function test_identity_helpers_are_null_when_nobody_is_authenticated(): void {
        $this->assertNull(user());
        $this->assertNull(member());
        $this->assertNull(vendor());
    }

    public function test_identity_helpers_read_through_the_shared_actor(): void {
        $user = new User();
        $member = $this->model();
        $vendor = $this->model();

        actor()->setUser($user);
        actor()->setMember($member);
        actor()->setVendor($vendor);

        $this->assertSame($user, user());
        $this->assertSame($member, member());
        $this->assertSame($vendor, vendor());
    }

}
