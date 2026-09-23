<?php //>

namespace Tests\Feature\Translation;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Translation\GeminiTranslateDriver;
use Tests\FeatureTestCase;

class GeminiTranslateDriverTest extends FeatureTestCase {

    public function test_a_successful_response_returns_the_translated_text(): void {
        Http::fake(['*' => Http::response($this->reply('<p>translated</p>'))]);

        $translated = (new GeminiTranslateDriver())->translate('<p>Hello</p>', 'en', 'tw');

        $this->assertSame('<p>translated</p>', $translated);
    }

    public function test_the_request_sends_the_configured_key_and_model_with_the_source_text(): void {
        $this->useCfg('gemini-translate', ['api-key' => 'test-key', 'model' => 'test-model']);

        Http::fake(['*' => Http::response($this->reply('translated'))]);

        (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertStringEndsWith('/models/test-model:generateContent', $request->url());
            $this->assertSame('test-key', $request->header('x-goog-api-key')[0]);
            $this->assertStringContainsString('Hello', $this->prompt($request));

            return true;
        });
    }

    public function test_a_locale_outside_the_map_is_sent_as_is(): void {
        $this->useCfg('gemini-translate', ['prompt' => 'from :source to :target with :text']);

        Http::fake(['*' => Http::response($this->reply('translated'))]);

        (new GeminiTranslateDriver())->translate('Hello', 'th', 'tw');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('from th to zh-TW with Hello', $this->prompt($request));

            return true;
        });
    }

    public function test_the_prompt_comes_from_the_bundle_with_the_locales_mapped(): void {
        $this->useCfg('gemini-translate', ['prompt' => 'from :source to :target with :text']);

        Http::fake(['*' => Http::response($this->reply('translated'))]);

        (new GeminiTranslateDriver())->translate('Hello', 'jp', 'cn');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('from ja to zh-CN with Hello', $this->prompt($request));

            return true;
        });
    }

    public function test_a_prompt_without_the_text_token_is_refused_as_a_driver_error(): void {
        $this->useCfg('gemini-translate', ['prompt' => 'translate from :source to :target']);

        $this->refuses('invalid-translation-driver', fn () => (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw'));
    }

    public function test_an_unfinished_answer_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response($this->reply('translated', 'MAX_TOKENS'))]);

        $this->refuses('translation-request-failed', fn () => (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw'));
    }

    public function test_a_blocked_answer_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);

        $this->refuses('translation-request-failed', fn () => (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw'));
    }

    public function test_an_empty_answer_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response($this->reply('   '))]);

        $this->refuses('translation-request-failed', fn () => (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw'));
    }

    public function test_an_error_reported_by_the_provider_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid']], 400)]);

        $this->refuses('translation-request-failed', fn () => (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw'));
    }

    public function test_a_transport_level_failure_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response('', 500)]);

        $this->refuses('translation-request-failed', fn () => (new GeminiTranslateDriver())->translate('Hello', 'en', 'tw'));
    }

    private function prompt(Request $request): string {
        return strval($request['contents'][0]['parts'][0]['text']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reply(string $text, string $reason = 'STOP'): array {
        return ['candidates' => [['finishReason' => $reason, 'content' => ['parts' => [['text' => $text]]]]]];
    }

}
