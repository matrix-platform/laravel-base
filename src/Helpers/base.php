<?php //>

use MatrixPlatform\Exceptions\ServiceException;

/**
 * @param array<string|int, mixed>|null $array
 */
function array_get_value(?array $array, string|int $key, mixed $default = null): mixed {
    return $array !== null && array_key_exists($key, $array) ? $array[$key] : $default;
}

function error(string $error, int $code = 500): never {
    throw new ServiceException($error, $code);
}

/**
 * @return list<string>
 */
function tokenize(?string $text): array {
    return preg_split('/[\s;,]+/', (string) $text, 0, PREG_SPLIT_NO_EMPTY) ?: [];
}
