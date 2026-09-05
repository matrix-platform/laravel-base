<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Translation\Driver;

class OkTranslationDriver implements Driver {

    public static ?string $requestedText = null;

    public static ?string $requestedSourceLocale = null;

    public static ?string $requestedTargetLocale = null;

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        self::$requestedText = $text;
        self::$requestedSourceLocale = $sourceLocale;
        self::$requestedTargetLocale = $targetLocale;

        return "translated: {$text}";
    }

}
