<?php //>

namespace MatrixPlatform\Services;

use MatrixPlatform\Models\City;
use MatrixPlatform\Models\Menu;

class CommonService {

    public function city() {
        return City::with('areas')
            ->orderBy('ranking')
            ->get();
    }

    public function menu($parent_id) {
        return Menu::whereActive()
            ->where('parent_id', $parent_id)
            ->orderBy('ranking')
            ->get();
    }

}
