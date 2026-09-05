<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Translation\Driver;

class ShufflingTranslationDriver implements Driver {

    public function translate(string $text, string $sourceLocale, string $targetLocale): string {
        preg_match_all('/<span[^>]*><\/span>/', $text, $matches);

        return 'translated start ' . implode(' inserted text ', array_reverse($matches[0])) . ' translated end';
    }

}
