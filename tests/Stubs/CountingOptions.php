<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\StaticOptions;

class CountingOptions extends StaticOptions {

    public int $calls = 0;

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null): array {
        $this->calls++;

        return parent::options($model);
    }

}
