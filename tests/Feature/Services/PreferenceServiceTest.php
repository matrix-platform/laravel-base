<?php //>

namespace Tests\Feature\Services;

use Illuminate\Validation\ValidationException;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\PreferenceService;
use MatrixPlatform\Support\CfgSchema;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\Widget;

class PreferenceServiceTest extends FeatureTestCase {

    private function service(): PreferenceService {
        return app(PreferenceService::class);
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

    public function test_an_identity_of_an_unsupported_model_type_is_rejected(): void {
        $this->refuses('invalid-identity-type', fn () => $this->service()->get(new Widget()));
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

    public function test_a_declared_key_reads_back_the_cfg_default_until_saved(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->assertSame(20, $this->service()->value($user, 'page-size'));

        $this->service()->save($user, ['page-size' => 50], true);

        $this->assertSame(50, $this->service()->value($user, 'page-size'));
    }

    public function test_an_admin_override_of_the_cfg_bundle_becomes_the_new_default(): void {
        $this->usePreferenceFixtures();
        $this->useCfg('preference-user', ['page-size' => 30]);

        $user = UserFactory::new()->createOne();

        $this->assertSame(30, $this->service()->value($user, 'page-size'));
        $this->assertSame(30, $this->service()->settings($user)['default']['page-size']);
    }

    public function test_reading_an_undeclared_key_is_refused(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->refuses('invalid-preference-key', fn () => $this->service()->value($user, 'theme'));
    }

    public function test_the_helper_reads_the_current_identity(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        actor()->setUser($user);

        $this->service()->save($user, ['locale' => 'en'], true);

        $this->assertSame('en', preference('locale'));
    }

    public function test_saving_a_declared_key_coerces_it_to_the_declared_type(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $payload = $this->service()->save($user, ['page-size' => '50', 'digest' => '0'], true);

        $this->assertSame(['digest' => false, 'page-size' => 50], $payload);
    }

    public function test_saving_an_invalid_declared_value_is_a_validation_failure(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        try {
            $this->service()->save($user, ['locale' => 'fr', 'page-size' => 0], true);

            $this->fail('expected a validation failure');
        } catch (ValidationException $exception) {
            $this->assertSame(['locale', 'page-size'], array_keys($exception->errors()));
        }

        $this->assertSame([], $this->service()->get($user));
    }

    public function test_clearing_a_declared_key_or_saving_its_default_drops_it(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['locale' => 'en', 'page-size' => 50, 'theme' => 'dark'], true);

        $payload = $this->service()->save($user, ['locale' => null, 'page-size' => 20], true);

        $this->assertSame(['theme' => 'dark'], $payload);
    }

    public function test_a_readonly_declared_key_is_never_written(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->assertSame([], $this->service()->save($user, ['code' => 'B2'], true));
    }

    public function test_saving_without_merge_resets_the_declared_keys_left_out(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['locale' => 'en', 'theme' => 'dark'], true);

        $this->assertSame(['page-size' => 50], $this->service()->save($user, ['page-size' => 50], false));
    }

    public function test_settings_lists_the_declared_columns_with_the_effective_values(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['locale' => 'en', 'theme' => 'dark'], true);

        $payload = $this->service()->settings($user);
        $locale = $this->columnByName($payload['columns'], 'locale');

        $this->assertSame(['returnToList', 'avatar', 'code', 'digest', 'locale', 'page-size', 'token'], array_column($payload['columns'], 'name'));
        $this->assertSame('Language', $locale['title']);
        $this->assertSame('select', $locale['presentation']);
        $this->assertSame(['string', 'in:tw,en'], $locale['rule']);
        $this->assertSame(['returnToList' => true, 'avatar' => [], 'code' => 'A1', 'digest' => true, 'locale' => 'en', 'page-size' => 20, 'token' => ''], $payload['data']);
        $this->assertSame(['returnToList' => true, 'avatar' => [], 'code' => 'A1', 'digest' => true, 'locale' => 'tw', 'page-size' => 20, 'token' => ''], $payload['default']);
    }

    public function test_update_writes_only_the_declared_keys_and_returns_the_settings(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['theme' => 'dark'], true);

        $payload = $this->service()->update($user, ['locale' => 'en', 'theme' => 'light']);

        $this->assertSame('en', $payload['data']['locale']);
        $this->assertSame(['theme' => 'dark', 'locale' => 'en'], $this->service()->get($user));
    }

    public function test_an_identity_type_without_a_declaration_has_no_settings(): void {
        $this->usePreferenceFixtures();

        $member = MemberFactory::new()->createOne();

        $this->assertSame(['columns' => [], 'data' => [], 'default' => []], $this->service()->settings($member));
        $this->assertSame(['theme' => 'dark'], $this->service()->save($member, ['theme' => 'dark'], true));
    }

    public function test_a_drive_declaration_for_an_identity_other_than_a_user_is_refused(): void {
        $this->usePreferenceFixtures();

        $vendor = VendorFactory::new()->createOne();

        $this->refuses('invalid-preference-column', fn () => $this->service()->settings($vendor));
    }

    public function test_a_secret_key_is_stored_as_is_but_masked_on_the_way_out(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->assertSame('system-token', $this->service()->value($user, 'token'));
        $this->assertSame('', $this->service()->settings($user)['data']['token']);

        $this->service()->save($user, ['token' => 'my-token'], true);

        $this->assertSame('my-token', $this->service()->value($user, 'token'));
        $this->assertSame(CfgSchema::MASK, $this->service()->settings($user)['data']['token']);
        $this->assertSame(['token' => CfgSchema::MASK], $this->service()->mask($user, $this->service()->get($user)));
    }

    public function test_sending_the_mask_back_keeps_the_stored_secret_with_or_without_merge(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->service()->save($user, ['token' => 'my-token'], true);
        $this->service()->update($user, ['token' => CfgSchema::MASK, 'locale' => 'en']);

        $this->assertSame(['token' => 'my-token', 'locale' => 'en'], $this->service()->get($user));

        $this->service()->save($user, ['token' => CfgSchema::MASK], false);

        $this->assertSame(['token' => 'my-token'], $this->service()->get($user));
    }

    public function test_sending_the_mask_without_a_stored_secret_stores_nothing(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne();

        $this->assertSame(['locale' => 'en'], $this->service()->save($user, ['token' => CfgSchema::MASK, 'locale' => 'en'], true));
        $this->assertSame('system-token', $this->service()->value($user, 'token'));
    }

    public function test_a_user_stores_a_picked_drive_image_as_a_file_entry(): void {
        $this->usePreferenceFixtures();

        $user = UserFactory::new()->createOne(['id' => User::ROOT]);
        $node = new DriveNode();

        $node->parent_id = DriveNode::ROOT;
        $node->type = DriveNodeType::File;
        $node->name = 'avatar.png';
        $node->hash = 'hash-avatar';
        $node->path = date('Ym') . '/' . str()->random(32);
        $node->size = 100;
        $node->mime_type = 'image/png';
        $node->width = 64;
        $node->height = 64;

        $node->save();

        actor()->setUser($user);

        $this->service()->update($user, ['avatar' => [['id' => $node->id]]]);

        $avatar = $this->service()->value($user, 'avatar');

        $this->assertIsArray($avatar);
        $this->assertSame(File::DRIVE_PREFIX . $node->path, $avatar[0]['path']);
        $this->assertSame('avatar.png', $avatar[0]['name']);
    }

}
