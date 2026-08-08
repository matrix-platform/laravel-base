<?php //>

namespace MatrixPlatform\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 * @extends Builder<TModel>
 */
class BaseBuilder extends Builder {

    public function whereActive(string $enable = 'enable_time', string $disable = 'disable_time'): static {
        return $this->whereExpired($enable)->whereNotExpired($disable);
    }

    public function whereExpired(string $column = 'expire_time'): static {
        return $this->where(function ($query) use ($column): void {
            $query->whereNotNull($column)->where($column, '<=', now());
        });
    }

    public function whereNotExpired(string $column = 'expire_time'): static {
        return $this->where(function ($query) use ($column): void {
            $query->whereNull($column)->orWhere($column, '>', now());
        });
    }

}
