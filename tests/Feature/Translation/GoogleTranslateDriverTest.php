<?php //>

namespace Tests\Feature\Translation;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Translation\GoogleTranslateDriver;
use Tests\FeatureTestCase;

class GoogleTranslateDriverTest extends FeatureTestCase {

    public function test_a_provider_without_an_api_key_reports_itself_unconfigured_without_any_request(): void {
        Http::fake();

        $this->assertNull((new GoogleTranslateDriver())->translate('Hello', 'en', 'tw'));

        Http::assertNothingSent();
    }

    public function test_a_successful_response_returns_the_translated_text(): void {
        Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => '<p>你好</p>']]]])]);

        $translated = $this->driver()->translate('<p>Hello</p>', 'en', 'tw');

        $this->assertSame('<p>你好</p>', $translated);
    }

    public function test_the_request_sends_the_configured_key_and_maps_locales_with_format_html(): void {
        Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => 'ok']]]])]);

        $this->driver(['api-key' => 'test-key'])->translate('Hello', 'en', 'tw');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertStringContainsString('key=test-key', $request->url());
            $this->assertSame('Hello', $request['q']);
            $this->assertSame('en', $request['source']);
            $this->assertSame('zh-TW', $request['target']);
            $this->assertSame('html', $request['format']);

            return true;
        });
    }

    public function test_the_cn_locale_maps_to_zh_cn(): void {
        Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => 'ok']]]])]);

        $this->driver()->translate('Hello', 'en', 'cn');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('zh-CN', $request['target']);

            return true;
        });
    }

    public function test_the_jp_locale_maps_to_ja(): void {
        Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => 'ok']]]])]);

        $this->driver()->translate('Hello', 'en', 'jp');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('ja', $request['target']);

            return true;
        });
    }

    public function test_an_error_reported_by_the_provider_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response(['error' => ['code' => 400, 'message' => 'Invalid Value']], 400)]);

        $this->refuses('translation-request-failed', fn () => $this->driver()->translate('Hello', 'en', 'tw'));
    }

    public function test_a_transport_level_failure_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response('', 500)]);

        $this->refuses('translation-request-failed', fn () => $this->driver()->translate('Hello', 'en', 'tw'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function driver(array $values = []): GoogleTranslateDriver {
        $this->useCfg('google-translate', ['api-key' => 'test-key', ...$values]);

        return new GoogleTranslateDriver();
    }

}
