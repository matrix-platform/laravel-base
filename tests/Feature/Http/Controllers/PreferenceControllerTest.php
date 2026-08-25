<?php //>

namespace Tests\Feature\Http\Controllers;

use MatrixPlatform\Models\IdentityType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;

class PreferenceControllerTest extends FeatureTestCase {

    /**
     * @return array<string, array{IdentityType}>
     */
    public static function identities(): array {
        return [
            'member' => [IdentityType::Member],
            'user' => [IdentityType::User],
            'vendor' => [IdentityType::Vendor]
        ];
    }

    private function prefix(IdentityType $type): string {
        return match ($type) {
            IdentityType::Member => 'api/member/preference',
            IdentityType::User => 'admin/user/preference',
            IdentityType::Vendor => 'vendor/preference'
        };
    }

    private function token(IdentityType $type, int $id = 1): string {
        return match ($type) {
            IdentityType::Member => MemberFactory::new()->createOne(['id' => $id])->createToken(),
            IdentityType::User => UserFactory::new()->createOne(['id' => $id])->createToken(),
            IdentityType::Vendor => VendorFactory::new()->createOne(['id' => $id])->createToken()
        };
    }

    public function test_reading_without_a_token_is_refused(): void {
        $this->postJson('admin/user/preference/get')->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_saving_without_a_token_is_refused(): void {
        $this->postJson('admin/user/preference/save', ['data' => ['theme' => 'dark']])->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    #[DataProvider('identities')]
    public function test_a_fresh_identity_reads_back_an_empty_object(IdentityType $type): void {
        $token = $this->token($type);

        $this->withToken($token)
            ->postJson($this->prefix($type) . '/get')
            ->assertExactJson(['success' => true, 'data' => []]);
    }

    #[DataProvider('identities')]
    public function test_saving_without_merge_overwrites_and_reading_returns_the_stored_data(IdentityType $type): void {
        $token = $this->token($type);
        $prefix = $this->prefix($type);

        $this->withToken($token)
            ->postJson("{$prefix}/save", ['data' => ['theme' => 'dark', 'locale' => 'tw']])
            ->assertJson(['success' => true]);

        $this->withToken($token)
            ->postJson("{$prefix}/save", ['data' => ['theme' => 'light']])
            ->assertExactJson(['success' => true, 'data' => ['theme' => 'light']]);

        $this->withToken($token)
            ->postJson("{$prefix}/get")
            ->assertExactJson(['success' => true, 'data' => ['theme' => 'light']]);
    }

    #[DataProvider('identities')]
    public function test_saving_with_merge_keeps_the_untouched_keys(IdentityType $type): void {
        $token = $this->token($type);
        $prefix = $this->prefix($type);

        $this->withToken($token)->postJson("{$prefix}/save", ['data' => ['theme' => 'dark', 'locale' => 'tw']]);

        $response = $this->withToken($token)->postJson("{$prefix}/save", ['data' => ['theme' => 'light'], 'merge' => true]);

        $response->assertExactJson(['success' => true, 'data' => ['theme' => 'light', 'locale' => 'tw']]);
    }

    #[DataProvider('identities')]
    public function test_saving_without_the_data_field_is_a_validation_failure(IdentityType $type): void {
        $token = $this->token($type);

        $this->withToken($token)
            ->postJson($this->prefix($type) . '/save')
            ->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
    }

    public function test_two_identities_with_the_same_id_do_not_share_preferences(): void {
        $userToken = $this->token(IdentityType::User);
        $memberToken = $this->token(IdentityType::Member);

        $this->withToken($userToken)->postJson('admin/user/preference/save', ['data' => ['role' => 'user']]);
        $this->withToken($memberToken)->postJson('api/member/preference/save', ['data' => ['role' => 'member']]);

        $this->withToken($userToken)
            ->postJson('admin/user/preference/get')
            ->assertExactJson(['success' => true, 'data' => ['role' => 'user']]);

        $this->withToken($memberToken)
            ->postJson('api/member/preference/get')
            ->assertExactJson(['success' => true, 'data' => ['role' => 'member']]);
    }

}
