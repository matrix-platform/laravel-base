<?php //>

namespace MatrixPlatform\Models\Builders;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class BaseBuilder extends Builder {

    const MAX_KEY = 2147483647;

    private static function validKey(mixed $id): bool {
        if (is_int($id)) {
            return $id >= 0 && $id <= self::MAX_KEY;
        }

        return is_string($id) && ctype_digit($id) && intval($id) <= self::MAX_KEY;
    }

    public function whereActive(string $enable = 'enable_time', string $disable = 'disable_time'): static {
        return $this->whereExpired($enable)->whereNotExpired($disable);
    }

    public function whereExpired(string $column = 'expire_time'): static {
        $column = $this->qualifyColumn($column);

        return $this->where(function ($query) use ($column): void {
            $query->whereNotNull($column)->where($column, '<=', now());
        });
    }

    /**
     * @param mixed $id
     * @return $this
     */
    public function whereKey($id) {
        if ($id === null || $id instanceof Model || !in_array($this->model->getKeyType(), ['int', 'integer'], true)) {
            return parent::whereKey($id);
        }

        if (is_array($id) || $id instanceof Arrayable) {
            $keys = array_values(array_filter(is_array($id) ? $id : $id->toArray(), self::validKey(...)));

            return $keys === [] ? $this->whereRaw('1 = 0') : parent::whereKey($keys);
        }

        return self::validKey($id) ? parent::whereKey($id) : $this->whereRaw('1 = 0');
    }

    public function whereNotExpired(string $column = 'expire_time'): static {
        $column = $this->qualifyColumn($column);

        return $this->where(function ($query) use ($column): void {
            $query->whereNull($column)->orWhere($column, '>', now());
        });
    }

}
