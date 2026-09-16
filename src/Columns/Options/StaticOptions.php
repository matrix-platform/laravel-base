<?php //>

namespace MatrixPlatform\Columns\Options;

use Illuminate\Database\Eloquent\Model;

class StaticOptions implements OptionProvider {

    /**
     * @param list<Option> $options
     */
    public function __construct(private array $options) {}

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null, bool $trashed = false): array {
        return $this->options;
    }

}
