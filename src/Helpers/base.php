<?php //>

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Actor;
use MatrixPlatform\Support\Resources;

function actor(): Actor {
    return app(Actor::class);
}

/**
 * @param array<string|int, mixed>|null $array
 */
function array_get_value(?array $array, string|int $key, mixed $default = null): mixed {
    return $array !== null && array_key_exists($key, $array) ? $array[$key] : $default;
}

function cfg(string $token, mixed $default = null): mixed {
    return app(Resources::class)->config($token, $default);
}

/**
 * @param array<string, mixed> $extra
 */
function error(string $error, int $code = 500, array $extra = []): never {
    throw new ServiceException($error, $code, $extra);
}

function i18n(string $token, ?string $locale = null): string {
    return app(Resources::class)->translate($token, $locale);
}

function invalid(string $field, string $error): never {
    error('validation-failed', 422, ['fields' => [$field => [$error]]]);
}

/**
 * @return list<string>
 */
function locales(): array {
    $locales = config('matrix.locales');

    return tokenize(is_string($locales) ? $locales : null);
}

function member(): ?Model {
    return actor()->member();
}

/**
 * @template T of object
 * @param class-string<T> $contract
 * @return T|null
 */
function resolve_driver(string $bundle, string $contract, string $error): ?object {
    $class = cfg("{$bundle}.driver");

    if ($class === null) {
        return null;
    }

    if (!is_string($class) || !is_a($class, $contract, true)) {
        error($error);
    }

    return app($class);
}

/**
 * @return list<string>
 */
function tokenize(?string $text): array {
    return preg_split('/[\s;,]+/', (string) $text, 0, PREG_SPLIT_NO_EMPTY) ?: [];
}

function user(): ?User {
    return actor()->user();
}

function vendor(): ?Model {
    return actor()->vendor();
}
