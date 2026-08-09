<?php //>

namespace Tests\Unit\Support;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Actor;
use PHPUnit\Framework\TestCase;

class ActorTest extends TestCase {

    private function model(): Model {
        return new class extends Model {};
    }

    public function test_every_identity_is_null_before_assignment(): void {
        $actor = new Actor();

        $this->assertNull($actor->user());
        $this->assertNull($actor->member());
        $this->assertNull($actor->vendor());
    }

    public function test_assigning_one_identity_leaves_the_others_null(): void {
        $actor = new Actor();
        $actor->setUser(new User());

        $this->assertNull($actor->member());
        $this->assertNull($actor->vendor());
    }

    public function test_the_three_identities_are_carried_at_the_same_time(): void {
        $user = new User();
        $member = $this->model();
        $vendor = $this->model();

        $actor = new Actor();
        $actor->setUser($user);
        $actor->setMember($member);
        $actor->setVendor($vendor);

        $this->assertSame($user, $actor->user());
        $this->assertSame($member, $actor->member());
        $this->assertSame($vendor, $actor->vendor());
    }

    public function test_require_user_returns_the_assigned_user(): void {
        $user = new User();
        $actor = new Actor();
        $actor->setUser($user);

        $this->assertSame($user, $actor->requireUser());
    }

    public function test_require_user_fails_with_401_when_nobody_is_authenticated(): void {
        $actor = new Actor();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-token');
        $this->expectExceptionCode(401);

        $actor->requireUser();
    }

    public function test_reassigning_the_user_fails(): void {
        $actor = new Actor();
        $actor->setUser(new User());

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('actor-already-assigned');
        $this->expectExceptionCode(500);

        $actor->setUser(new User());
    }

    public function test_reassigning_the_member_fails(): void {
        $actor = new Actor();
        $actor->setMember($this->model());

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('actor-already-assigned');

        $actor->setMember($this->model());
    }

    public function test_reassigning_the_vendor_fails(): void {
        $actor = new Actor();
        $actor->setVendor($this->model());

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('actor-already-assigned');

        $actor->setVendor($this->model());
    }

}
