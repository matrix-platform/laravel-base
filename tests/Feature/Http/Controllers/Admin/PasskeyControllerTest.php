<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use MatrixPlatform\Models\PasskeyCredential;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Factories\PasskeyCredentialFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Support\PasskeyAuthenticator;

class PasskeyControllerTest extends FeatureTestCase {

    private const ORIGIN = 'https://example.com';
    private const RP_ID = 'example.com';

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.passkey-rp-id' => self::RP_ID]);
    }

    private function token(int $id = 1): string {
        return UserFactory::new()->createOne(['id' => $id])->createToken();
    }

    public function test_registration_options_require_a_token(): void {
        $this->postJson('admin/user/passkey/register/options')->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_the_full_register_list_rename_delete_cycle(): void {
        $token = $this->token(5);
        $authenticator = new PasskeyAuthenticator();

        $options = $this->withToken($token)
            ->postJson('admin/user/passkey/register/options')
            ->json('data');
        $challenge = Base64UrlSafe::decodeNoPadding($options['challenge']);
        $response = $authenticator->registrationResponse($challenge, self::RP_ID, self::ORIGIN);

        $id = $this->withToken($token)
            ->postJson('admin/user/passkey/register', [
                'challenge' => $options['challenge'],
                'credential' => $response,
                'name' => 'my device'
            ])
            ->assertJson(['success' => true])
            ->json('data.id');

        $rows = $this->withToken($token)
            ->postJson('admin/user/passkey')
            ->json('data');
        $this->assertSame(['my device'], array_column($rows, 'name'));

        $this->withToken($token)
            ->postJson("admin/user/passkey/{$id}/rename", ['name' => 'renamed'])
            ->assertJson(['success' => true]);
        $this->assertSame('renamed', PasskeyCredential::query()->findOrFail((int) $id)->name);

        $this->withToken($token)
            ->postJson("admin/user/passkey/{$id}/delete")
            ->assertJson(['success' => true]);
        $this->assertSame(0, PasskeyCredential::query()->count());
    }

    public function test_a_user_cannot_delete_another_users_passkey(): void {
        UserFactory::new()->createOne(['id' => 5]);
        $owner = PasskeyCredentialFactory::new()->createOne(['user_id' => 5]);

        $this->withToken($this->token(6))
            ->postJson("admin/user/passkey/{$owner->id}/delete")
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);

        $this->assertSame(1, PasskeyCredential::query()->count());
    }

    public function test_a_user_cannot_rename_another_users_passkey(): void {
        UserFactory::new()->createOne(['id' => 5]);
        $owner = PasskeyCredentialFactory::new()->createOne(['user_id' => 5, 'name' => 'original']);

        $this->withToken($this->token(6))
            ->postJson("admin/user/passkey/{$owner->id}/rename", ['name' => 'stolen'])
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);

        $this->assertSame('original', PasskeyCredential::query()->findOrFail($owner->id)->name);
    }

}
