<?php //>

namespace MatrixPlatform\Services\Admin;

use MatrixPlatform\Support\Resources;

class I18nService {

    public function get($name) {
        return app(Resources::class)->getI18nBundle($name);
    }

}
