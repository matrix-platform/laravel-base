<?php //>

namespace MatrixPlatform\Services\Admin;

use MatrixPlatform\Support\Resources;

class I18nService {

    public function __construct(private Resources $resources) {}

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array {
        return $this->resources->getI18nBundle($name);
    }

}
