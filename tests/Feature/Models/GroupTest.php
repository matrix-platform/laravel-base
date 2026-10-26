<?php //>

namespace Tests\Feature\Models;

use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class GroupTest extends FeatureTestCase {

    public function test_users_are_the_accounts_assigned_to_the_group(): void {
        $group = GroupFactory::new()->createOne();
        $member = UserFactory::new()->createOne(['group_id' => $group->id]);

        UserFactory::new()->createOne(['group_id' => GroupFactory::new()->createOne()->id]);
        UserFactory::new()->createOne();

        $this->assertSame([$member->id], $group->users()->pluck('id')->all());
    }

}
