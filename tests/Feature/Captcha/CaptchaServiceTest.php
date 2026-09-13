<?php //>

namespace Tests\Feature\Captcha;

use MatrixPlatform\Captcha\CaptchaService;
use MatrixPlatform\Captcha\ImageDriver;
use MatrixPlatform\Captcha\NoneDriver;
use Tests\FeatureTestCase;

class CaptchaServiceTest extends FeatureTestCase {

    private function service(): CaptchaService {
        return app(CaptchaService::class);
    }

    public function test_the_given_provider_is_resolved_to_its_driver(): void {
        $this->assertInstanceOf(ImageDriver::class, $this->service()->driver('captcha-image'));
        $this->assertInstanceOf(NoneDriver::class, $this->service()->driver('captcha-none'));
    }

    public function test_a_provider_that_resolves_to_no_driver_is_refused_rather_than_disabling_the_captcha(): void {
        $this->refuses('invalid-captcha-driver', fn () => $this->service()->driver('does-not-exist'));
    }

    public function test_an_empty_provider_is_refused_rather_than_read_as_off(): void {
        $this->refuses('invalid-captcha-driver', fn () => $this->service()->driver(''));
    }

    public function test_the_none_driver_issues_no_challenge_and_accepts_any_code(): void {
        $driver = $this->service()->driver('captcha-none');

        $this->assertNull($driver->generate());
        $this->assertTrue($driver->verify('', ''));
    }

}
