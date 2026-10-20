<?php //>

namespace MatrixPlatform\Translation;

interface Driver {

    /**
     * @return string|null
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): ?string;

}
