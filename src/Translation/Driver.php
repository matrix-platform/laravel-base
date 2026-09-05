<?php //>

namespace MatrixPlatform\Translation;

interface Driver {

    public function translate(string $text, string $sourceLocale, string $targetLocale): string;

}
