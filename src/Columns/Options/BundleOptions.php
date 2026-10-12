<?php //>

namespace MatrixPlatform\Columns\Options;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Support\Resources;

class BundleOptions implements OptionProvider {

    public function __construct(private string $name) {}

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null, bool $trashed = false): array {
        $options = [];

        foreach (app(Resources::class)->getI18nBundle("options/{$this->name}") ?: [] as $id => $title) {
            $options[] = new Option([], $id, count($options), is_string($title) ? $title : (string) $id);
        }

        return $options;
    }

}
