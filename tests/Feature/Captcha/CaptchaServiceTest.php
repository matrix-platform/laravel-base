<?php //>

namespace Tests\Feature\Captcha;

use MatrixPlatform\Captcha\CaptchaService;
use MatrixPlatform\Captcha\ImageDriver;
use MatrixPlatform\Captcha\NoneDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

class CaptchaServiceTest extends FeatureTestCase {

    private function service(): CaptchaService {
        return app(CaptchaService::class);
    }

    public function test_the_given_provider_is_resolved_to_its_driver(): void {
        $this->assertInstanceOf(ImageDriver::class, $this->service()->driver('captcha-image'));
        $this->assertInstanceOf(NoneDriver::class, $this->service()->driver('captcha-none'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providersWithoutADriver(): array {
        return [
            'unknown' => ['does-not-exist'],
            'empty' => ['']
        ];
    }

    #[DataProvider('providersWithoutADriver')]
    public function test_a_provider_that_resolves_to_no_driver_is_refused(string $provider): void {
        $this->refuses('invalid-captcha-driver', fn () => $this->service()->driver($provider));
    }

    public function test_the_none_driver_issues_no_challenge_and_accepts_any_code(): void {
        $driver = $this->service()->driver('captcha-none');

        $this->assertNull($driver->generate());
        $this->assertTrue($driver->verify('', ''));
    }

}
