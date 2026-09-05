<?php //>

namespace Tests\Feature\Services\Admin;

use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\MfaService;
use PragmaRX\Google2FA\Google2FA;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class MfaServiceTest extends FeatureTestCase {

    private function enableMfa(User $user): void {
        app(MfaService::class)->setup($user);
        app(MfaService::class)->confirm($user, (new Google2FA())->getCurrentOtp($this->secretOf($user)));
    }

    private function reload(User $user): User {
        return User::query()->findOrFail($user->id);
    }

    private function secretOf(User $user): string {
        $secret = $this->reload($user)->secret;

        if ($secret === null) {
            $this->fail('expected secret to be set');
        }

        return $secret;
    }

    private function trustDays(): int {
        return (int) cfg('admin.mfa-trust-days');
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function trustedUser(): array {
        $user = UserFactory::new()->createOne();
        $this->enableMfa($user);

        return [$user, app(MfaService::class)->issueTrust($user)];
    }

    public function test_setup_returns_the_stored_secret_and_a_provisioning_uri(): void {
        $user = UserFactory::new()->createOne();

        $data = app(MfaService::class)->setup($user);

        $this->assertSame($this->secretOf($user), $data['secret']);
        $this->assertStringStartsWith('otpauth://', $data['uri']);
    }

    public function test_setup_refuses_when_mfa_is_already_enabled(): void {
        $user = UserFactory::new()->createOne();

        $this->enableMfa($user);

        $this->refuses('mfa-already-enabled', fn () => app(MfaService::class)->setup($user));
    }

    public function test_setup_does_not_enable_mfa_until_confirmed(): void {
        $user = UserFactory::new()->createOne();

        app(MfaService::class)->setup($user);

        $this->assertFalse($this->reload($user)->hasMfaEnabled());
    }

    public function test_confirm_with_the_wrong_code_reports_the_code_field(): void {
        $user = UserFactory::new()->createOne();

        app(MfaService::class)->setup($user);

        try {
            app(MfaService::class)->confirm($user, '000000');
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => ['code' => ['invalid-code']]], $exception->getExtra());

            return;
        }

        $this->fail('confirm() was expected to reject the wrong code');
    }

    public function test_confirm_with_the_right_code_enables_mfa(): void {
        $user = UserFactory::new()->createOne();

        $this->enableMfa($user);

        $this->assertTrue($this->reload($user)->hasMfaEnabled());
    }

    public function test_disable_clears_the_secret_and_confirmation(): void {
        $user = UserFactory::new()->createOne();

        $this->enableMfa($user);

        app(MfaService::class)->disable($user);

        $fresh = $this->reload($user);

        $this->assertNull($fresh->secret);
        $this->assertNull($fresh->confirmed_time);
    }

    public function test_verify_rejects_a_totp_code_outside_the_configured_window(): void {
        $this->useCfg('admin', ['mfa-window' => 0]);

        $user = UserFactory::new()->createOne();

        app(MfaService::class)->setup($user);

        $google2fa = new Google2FA();
        $stale = $google2fa->oathTotp($this->secretOf($user), $google2fa->getTimestamp() - 2);

        $this->assertFalse(app(MfaService::class)->verify($this->reload($user), $stale));
    }

    public function test_trusted_accepts_a_token_that_issue_trust_produced_for_the_same_user(): void {
        [$user, $token] = $this->trustedUser();

        $this->assertTrue(app(MfaService::class)->trusted($this->reload($user), $token));
    }

    public function test_trusted_rejects_a_null_token(): void {
        $user = UserFactory::new()->createOne();

        $this->assertFalse(app(MfaService::class)->trusted($user, null));
    }

    public function test_trusted_rejects_a_token_issued_for_a_different_user(): void {
        [, $token] = $this->trustedUser();
        $other = UserFactory::new()->createOne();

        $this->assertFalse(app(MfaService::class)->trusted($this->reload($other), $token));
    }

    public function test_trusted_rejects_an_expired_token(): void {
        [$user, $token] = $this->trustedUser();

        $this->travel($this->trustDays() + 1)->days();

        $this->assertFalse(app(MfaService::class)->trusted($this->reload($user), $token));
    }

    public function test_trusted_rejects_a_token_after_mfa_is_disabled_and_re_enabled(): void {
        [$user, $token] = $this->trustedUser();

        app(MfaService::class)->disable($this->reload($user));
        $this->enableMfa($this->reload($user));

        $this->assertFalse(app(MfaService::class)->trusted($this->reload($user), $token));
    }

    public function test_trusted_rejects_a_token_after_the_password_changes(): void {
        [$user, $token] = $this->trustedUser();

        $fresh = $this->reload($user);
        $fresh->password = 'a-new-Passw0rd';
        $fresh->save();

        $this->assertFalse(app(MfaService::class)->trusted($this->reload($user), $token));
    }

}
