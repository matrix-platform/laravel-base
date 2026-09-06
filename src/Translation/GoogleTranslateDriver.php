<?php //>

namespace MatrixPlatform\Translation;

use Illuminate\Support\Facades\Http;

class GoogleTranslateDriver implements Driver {

    private const LOCALE_MAP = [
        'cn' => 'zh-CN',
        'jp' => 'ja',
        'tw' => 'zh-TW'
    ];

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        $endpoint = strval(cfg('google-translate.endpoint'));
        $key = strval(cfg('google-translate.api-key'));

        $response = Http::withQueryParameters(['key' => $key])->post($endpoint, [
            'q' => $text,
            'source' => $this->mapLocale($sourceLocale),
            'target' => $this->mapLocale($targetLocale),
            'format' => 'html'
        ]);

        $translated = $response->json('data.translations.0.translatedText');

        if ($response->failed() || !is_string($translated)) {
            error('translation-request-failed');
        }

        return $translated;
    }

    private function mapLocale(string $locale): string {
        return strval(array_get_value(self::LOCALE_MAP, $locale, $locale));
    }

}
