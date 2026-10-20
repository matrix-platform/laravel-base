<?php //>

namespace MatrixPlatform\Services\Admin;

use MatrixPlatform\Support\Resources;

class I18nService {

    public function __construct(private Resources $resources) {}

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array {
        $path = 'i18n/' . app()->getLocale() . "/{$name}";

        if (!in_array(basename($path), $this->resources->bundleNames(dirname($path)), true)) {
            return null;
        }

        return $this->resources->getI18nBundle($name);
    }

}
