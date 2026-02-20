<?php //>

namespace MatrixPlatform\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class BaseBuilder extends Builder {

    public function whereActive($enable = 'enable_time', $disable = 'disable_time') {
        return $this->whereNotNull($enable)->where($enable, '<=', now())->whereNotExpired($disable);
    }

    public function whereNotExpired($column = 'expire_time') {
        return $this->where(fn ($query) => $query->whereNull($column)->orWhere($column, '>', now()));
    }

}
