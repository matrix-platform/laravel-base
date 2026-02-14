<?php //>

use MatrixPlatform\Resources;

function cfg($token, $default = null) {
    return app(Resources::class)->config($token, $default);
}

function i18n($token, $locale = null) {
    return app(Resources::class)->translate($token, $locale);
}

function isolate_require() {
    return require func_get_arg(0);
}

function tokenize($text) {
    return preg_split('/[\s;,]/', $text, 0, PREG_SPLIT_NO_EMPTY);
}
