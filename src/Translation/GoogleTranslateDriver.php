<?php //>

namespace MatrixPlatform\Translation;

use Illuminate\Support\Facades\Http;

class GoogleTranslateDriver implements Driver {

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        $endpoint = strval(cfg('google-translate.endpoint'));
        $key = strval(cfg('google-translate.api-key'));

        $response = Http::withQueryParameters(['key' => $key])->post($endpoint, [
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
