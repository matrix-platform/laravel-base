<?php //>

namespace MatrixPlatform\Columns\Options;

use Illuminate\Database\Eloquent\Model;

interface OptionProvider {

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null, bool $trashed = false): array;

}
