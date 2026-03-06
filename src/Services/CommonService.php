<?php //>

namespace MatrixPlatform\Services;

use MatrixPlatform\Models\City;

class CommonService {

    public function city() {
        return City::with('areas')
            ->orderBy('ranking')
            ->get();
    }

}
