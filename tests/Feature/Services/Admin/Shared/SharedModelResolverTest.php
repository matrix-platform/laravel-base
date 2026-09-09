<?php //>

namespace Tests\Feature\Services\Admin\Shared;

use MatrixPlatform\Services\Admin\Shared\SharedModelResolver;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class SharedModelResolverTest extends FeatureTestCase {

    private function resolver(): SharedModelResolver {
        return app(SharedModelResolver::class);
    }

    public function test_a_prefix_with_no_mounted_controller_is_refused_as_an_unsupported_model(): void {
        $this->actAsRoot();

        $this->refusesField('prefix', 'unsupported-model', fn () => $this->resolver()->resolve('nowhere', 1, 'query'));
    }

    public function test_a_missing_record_is_reported_as_not_found(): void {
        $this->actAsRoot();

        $this->refuses('data-not-found', fn () => $this->resolver()->resolve('group', 999999, 'query'));
    }

    public function test_a_regular_user_without_the_matching_permission_is_denied(): void {
        $group = GroupFactory::new()->createOne();

        actor()->setUser(UserFactory::new()->createOne(['id' => 1001, 'permissions' => []]));

        $this->refuses('permission-denied', fn () => $this->resolver()->resolve('group', $group->id, 'query'));
    }

    public function test_a_regular_user_with_the_matching_permission_is_allowed(): void {
        $group = GroupFactory::new()->createOne();

        actor()->setUser(UserFactory::new()->createOne(['id' => 1001, 'permissions' => ['group' => ['query' => true]]]));

        $this->assertSame($group->id, $this->resolver()->resolve('group', $group->id, 'query')->getKey());
    }

    public function test_root_bypasses_the_permission_check(): void {
        $group = GroupFactory::new()->createOne();

        $this->actAsRoot();

        $this->assertSame($group->id, $this->resolver()->resolve('group', $group->id, 'query')->getKey());
    }

}
