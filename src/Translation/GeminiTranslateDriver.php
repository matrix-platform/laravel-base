<?php //>

namespace MatrixPlatform\Translation;

use Illuminate\Support\Facades\Http;

class GeminiTranslateDriver implements Driver {

    private const CONNECT_TIMEOUT = 10;

    private const PATH = '/models/';

    private const TEXT_TOKEN = ':text';

    private const TIMEOUT = 60;

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        $endpoint = rtrim(strval(cfg('gemini-translate.endpoint')), '/') . self::PATH . strval(cfg('gemini-translate.model')) . ':generateContent';
        $key = strval(cfg('gemini-translate.api-key'));

        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->post($endpoint, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $this->prompt($text, $sourceLocale, $targetLocale)]]]],
                'generationConfig' => ['temperature' => 0, 'topP' => 0.8, 'topK' => 40]
            ]);

        $translated = $response->json('candidates.0.content.parts.0.text');
        $translated = is_string($translated) ? trim($translated) : '';

        if ($response->failed() || $response->json('candidates.0.finishReason') !== 'STOP' || $translated === '') {
            error('translation-request-failed');
        }

        return $translated;
    }

    private function prompt(string $text, string $sourceLocale, string $targetLocale): string {
        $template = strval(cfg('gemini-translate.prompt'));

        if (!str_contains($template, self::TEXT_TOKEN)) {
            error('invalid-translation-driver');
        }

        return strtr($template, [
            ':source' => Locale::code($sourceLocale),
            ':target' => Locale::code($targetLocale),
            self::TEXT_TOKEN => $text
        ]);
    }

}
