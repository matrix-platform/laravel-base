<?php //>

namespace App\Models;

use MatrixPlatform\Models\BaseModel;

class DriveProbe extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'stub_widget';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        $casts = ['payload' => 'array'];

        foreach (locales() as $locale) {
            $casts["gallery__{$locale}"] = 'array';
        }

        return $casts;
    }

}
