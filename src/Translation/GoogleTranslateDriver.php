<?php //>

namespace MatrixPlatform\Translation;

use Illuminate\Support\Facades\Http;

class GoogleTranslateDriver implements Driver {

    private const CONNECT_TIMEOUT = 10;

    private const TIMEOUT = 60;

    public function translate(string $text, string $sourceLocale, string $targetLocale): ?string {
        $endpoint = strval(cfg('google-translate.endpoint'));
        $key = strval(cfg('google-translate.api-key'));

        if ($key === '') {
            return null;
        }

        $response = Http::withQueryParameters(['key' => $key])
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->post($endpoint, [
                'q' => $text,
                'source' => Locale::code($sourceLocale),
                'target' => Locale::code($targetLocale),
                'format' => 'html'
            ]);

        $translated = $response->json('data.translations.0.translatedText');

        if ($response->failed() || !is_string($translated)) {
            error('translation-request-failed');
        }

        return $translated;
    }

}
