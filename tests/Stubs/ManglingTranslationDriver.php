<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Translation\Driver;

class ManglingTranslationDriver implements Driver {

    public static string $mangle = 'identity';

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        return match (self::$mangle) {
            'attribute-after' => strval(str_replace('"></span>', '" class="notranslate"></span>', $text)),
            'attribute-before' => strval(str_replace('<span ', '<span class="notranslate" ', $text)),
            'dropped-attribute' => strval(preg_replace('/<span[^>]*>/', '<span>', $text)),
            'spaced-equals' => strval(str_replace('data-token="', 'data-token = "', $text)),
            'unknown-token' => $text . '<span data-token="placeholder-9"></span>',
            'uppercase-tag' => strval(str_replace(['<span ', '</span>'], ['<SPAN ', '</SPAN>'], $text)),
            default => $text
        };
    }

}
