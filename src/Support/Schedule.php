<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Schedule {

    private static function future(mixed $value): bool {
        return ($value instanceof Carbon ? $value : Carbon::parse($value))->isFuture();
    }

    public static function isEnabled(Model $model, string $enableField, string $disableField): bool {
        $enable = $model->getAttribute($enableField);

        if ($enable === null || self::future($enable)) {
            return false;
        }

        $disable = $model->getAttribute($disableField);

        return $disable === null || self::future($disable);
    }

}
