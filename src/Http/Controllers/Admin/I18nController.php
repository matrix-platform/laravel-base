<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\I18nService;

class I18nController extends BaseController {

    public function __construct(private I18nService $service) {}

    #[Action('{name}')]
    public function get($name) {
        return $this->service->get($name);
    }

}
