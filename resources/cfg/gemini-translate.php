<?php //>

use MatrixPlatform\Translation\GeminiTranslateDriver;

return [

    'driver' => GeminiTranslateDriver::class,

    'api-key' => '',

    'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',

    'model' => 'gemini-flash-lite-latest',

    'prompt' => <<<'PROMPT'
        You are a professional translation engine.

        Translate the text below from language code ":source" into language code ":target".

        Rules:
        1. Return the translated text only. No explanation, no prefix, no Markdown.
        2. Keep every HTML tag and attribute exactly as it appears, above all marker elements such as <span data-token="..."></span>. Do not change a single character of them, and keep each one at the matching position in the translated text.
        3. Preserve the line breaks, punctuation, numbers, URLs and email addresses of the source.
        4. Keep brand, company and product names as they are, or use the name commonly used in the target language.
        5. Do not add anything the source does not say, and do not drop anything it does.
        6. If the source is already in the target language, rewrite it so that it reads naturally in that language.

        Source text:
        :text
        PROMPT,

];
