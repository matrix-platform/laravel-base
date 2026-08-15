<?php //>

namespace MatrixPlatform\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>|object|null>
 */
class PermissionMap implements CastsAttributes, ComparesCastableAttributes, SerializesCastableAttributes {

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool {
        return Arr::sortRecursive($this->decode($firstValue)) === Arr::sortRecursive($this->decode($secondValue));
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array {
        return $this->decode($value);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): object {
        return (object) $this->decode($value);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        return [$key => strval(json_encode((object) $this->canonical($value), JSON_UNESCAPED_UNICODE))];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function canonical(mixed $value): array {
        $data = [];

        foreach ($this->decode($value) as $path => $actions) {
            foreach (is_array($actions) ? $actions : [] as $action => $granted) {
                if ($granted == true) {
                    $data[strval($path)][strval($action)] = true;
                }
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array {
        if (is_object($value)) {
            $value = json_encode($value);
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        return is_array($data) ? $data : [];
    }

}
