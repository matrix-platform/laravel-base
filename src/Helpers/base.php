<?php //>

use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\Resources;

/**
 * @param array<string|int, mixed>|null $array
 */
function array_get_value(?array $array, string|int $key, mixed $default = null): mixed {
    return $array !== null && array_key_exists($key, $array) ? $array[$key] : $default;
}

function cfg(string $token, mixed $default = null): mixed {
    return app(Resources::class)->config($token, $default);
}

function error(string $error, int $code = 500): never {
    throw new ServiceException($error, $code);
}

function i18n(string $token, ?string $locale = null): string {
    return app(Resources::class)->translate($token, $locale);
}

/**
 * @return list<string>
 */
function tokenize(?string $text): array {
    return preg_split('/[\s;,]+/', (string) $text, 0, PREG_SPLIT_NO_EMPTY) ?: [];
}
