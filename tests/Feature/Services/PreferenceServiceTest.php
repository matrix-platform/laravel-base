<?php //>

namespace Tests\Feature\Services;

use MatrixPlatform\Services\PreferenceService;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;

class PreferenceServiceTest extends FeatureTestCase {

    private function service(): PreferenceService {
        return app(PreferenceService::class);
    }

    public function test_a_fresh_identity_has_no_preferences_yet(): void {
        $user = UserFactory::new()->createOne();

        $this->assertSame([], $this->service()->get($user));
    }

    public function test_saving_without_merge_stores_the_data_as_is(): void {
        $user = UserFactory::new()->createOne();

        $payload = $this->service()->save($user, ['theme' => 'dark'], false);

        $this->assertSame(['theme' => 'dark'], $payload);
        $this->assertSame(['theme' => 'dark'], $this->service()->get($user));
    }

    public function test_saving_without_merge_again_overwrites_the_previous_data(): void {
        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['theme' => 'dark', 'locale' => 'tw'], false);
        $this->service()->save($user, ['theme' => 'light'], false);

        $this->assertSame(['theme' => 'light'], $this->service()->get($user));
    }

    public function test_saving_with_merge_keeps_the_untouched_keys(): void {
        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['theme' => 'dark', 'locale' => 'tw'], false);

        $payload = $this->service()->save($user, ['theme' => 'light'], true);

        $this->assertSame(['theme' => 'light', 'locale' => 'tw'], $payload);
        $this->assertSame(['theme' => 'light', 'locale' => 'tw'], $this->service()->get($user));
    }

    public function test_merging_into_a_fresh_identity_behaves_like_a_plain_save(): void {
        $user = UserFactory::new()->createOne();

        $payload = $this->service()->save($user, ['theme' => 'dark'], true);

        $this->assertSame(['theme' => 'dark'], $payload);
    }

    public function test_each_identity_type_keeps_its_own_preferences_even_with_the_same_id(): void {
        $user = UserFactory::new()->createOne(['id' => 1]);
        $member = MemberFactory::new()->createOne(['id' => 1]);
        $vendor = VendorFactory::new()->createOne(['id' => 1]);

        $this->service()->save($user, ['role' => 'user'], false);
        $this->service()->save($member, ['role' => 'member'], false);
        $this->service()->save($vendor, ['role' => 'vendor'], false);

        $this->assertSame(['role' => 'user'], $this->service()->get($user));
        $this->assertSame(['role' => 'member'], $this->service()->get($member));
        $this->assertSame(['role' => 'vendor'], $this->service()->get($vendor));
    }

}
