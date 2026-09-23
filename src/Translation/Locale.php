<?php //>

namespace MatrixPlatform\Translation;

class Locale {

    private const MAP = [
        'cn' => 'zh-CN',
        'jp' => 'ja',
        'tw' => 'zh-TW'
    ];

    public static function code(string $locale): string {
        return strval(array_get_value(self::MAP, $locale, $locale));
    }

}
