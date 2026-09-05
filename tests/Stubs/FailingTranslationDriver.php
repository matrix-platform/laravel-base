<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Translation\Driver;

class FailingTranslationDriver implements Driver {

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        error('translation-request-failed');
    }

}
