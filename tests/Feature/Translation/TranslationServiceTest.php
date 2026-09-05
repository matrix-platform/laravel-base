<?php //>

namespace Tests\Feature\Translation;

use Illuminate\Support\Facades\Log;
use MatrixPlatform\Translation\TranslationService;
use Mockery;
use Tests\FeatureTestCase;
use Tests\Stubs\OkTranslationDriver;

class TranslationServiceTest extends FeatureTestCase {

    private function service(): TranslationService {
        return app(TranslationService::class);
    }

    public function test_a_source_or_target_locale_outside_the_configured_list_is_refused(): void {
        $this->refuses('invalid-locale', fn () => $this->service()->translate('Hello', 'fr', 'tw'));
        $this->refuses('invalid-locale', fn () => $this->service()->translate('Hello', 'en', 'fr'));
    }

    public function test_a_source_locale_equal_to_the_target_locale_is_refused(): void {
        $this->refuses('invalid-locale', fn () => $this->service()->translate('Hello', 'en', 'en'));
    }

    public function test_a_provider_with_no_driver_configured_returns_no_translation_instead_of_failing(): void {
        config()->set('matrix.translation-provider', 'does-not-exist');

        $this->assertNull($this->service()->translate('Hello', 'en', 'tw'));
    }

    public function test_a_provider_naming_a_class_that_is_not_a_driver_is_refused(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'broken');

        $this->refuses('invalid-translation-driver', fn () => $this->service()->translate('Hello', 'en', 'tw'));
    }

    public function test_the_configured_providers_driver_receives_the_text_and_its_result_is_restored_and_returned(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'stub');

        $translated = $this->service()->translate('Hello {name}', 'en', 'tw');

        $this->assertSame('Hello <span data-token="placeholder-0"></span>', OkTranslationDriver::$requestedText);
        $this->assertSame('en', OkTranslationDriver::$requestedSourceLocale);
        $this->assertSame('tw', OkTranslationDriver::$requestedTargetLocale);
        $this->assertSame('translated: Hello {name}', $translated);
    }

    public function test_placeholders_survive_the_driver_reordering_text_and_inserting_content_around_them(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'shuffling');

        $translated = strval($this->service()->translate('Hi {name}, your order {no} shipped. See {{name}} for details.', 'en', 'tw'));

        $this->assertStringContainsString('{name}', $translated);
        $this->assertStringContainsString('{no}', $translated);
        $this->assertStringContainsString('{{name}}', $translated);
    }

    public function test_a_placeholder_inside_an_html_attribute_value_is_left_untouched(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'stub');

        $this->service()->translate('<a href="/reset?token={code}">Reset</a>', 'en', 'tw');

        $this->assertSame('<a href="/reset?token={code}">Reset</a>', OkTranslationDriver::$requestedText);
    }

    public function test_a_successful_translation_is_recorded_to_the_application_log(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'stub');

        $spy = Log::spy();

        $this->service()->translate('Hello', 'en', 'tw');

        $spy->shouldHaveReceived('info', ['translation.translated', Mockery::on(fn (array $context) => array_get_value($context, 'action') === 'translated'
            && array_get_value($context, 'source') === 'en'
            && array_get_value($context, 'target') === 'tw'
            && array_get_value($context, 'length') === 5)]);
    }

    public function test_a_failed_translation_is_recorded_and_the_exception_still_propagates(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'failing');

        $spy = Log::spy();

        $this->refuses('translation-request-failed', fn () => $this->service()->translate('Hello', 'en', 'tw'));

        $spy->shouldHaveReceived('info', ['translation.failed', Mockery::on(fn (array $context) => array_get_value($context, 'action') === 'failed'
            && array_get_value($context, 'error') === 'translation-request-failed')]);
    }

    public function test_validation_failures_are_not_recorded(): void {
        $spy = Log::spy();

        $this->refuses('invalid-locale', fn () => $this->service()->translate('Hello', 'en', 'en'));

        $spy->shouldNotHaveReceived('info');
    }

}
