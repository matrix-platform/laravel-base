<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\Operator;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;

class OperatorTest extends FeatureTestCase {

    public function test_the_view_carries_the_declared_columns(): void {
        $this->assertEqualsCanonicalizing(['id', 'type', 'username'], Schema::getColumnListing('base_operator'));
    }

    public function test_the_view_exposes_every_identity_kind(): void {
        $user = UserFactory::new()->createOne(['username' => 'the-user']);
        $member = MemberFactory::new()->createOne(['username' => 'the-member']);
        $vendor = VendorFactory::new()->createOne(['username' => 'the-vendor']);

        $operators = Operator::query()
            ->orderBy('username')
            ->pluck('username', 'id')
            ->all();

        $this->assertSame(
            [$member->id => 'the-member', $user->id => 'the-user', $vendor->id => 'the-vendor'],
            $operators
        );
    }

    public function test_the_type_column_reads_back_as_an_identity_type(): void {
        UserFactory::new()->createOne(['username' => 'the-user']);
        MemberFactory::new()->createOne(['username' => 'the-member']);
        VendorFactory::new()->createOne(['username' => 'the-vendor']);

        $types = [];

        foreach (Operator::query()->orderBy('username')->get() as $operator) {
            $types[$operator->username] = $operator->type;
        }

        $this->assertSame(
            ['the-member' => IdentityType::Member, 'the-user' => IdentityType::User, 'the-vendor' => IdentityType::Vendor],
            $types
        );
    }

}
