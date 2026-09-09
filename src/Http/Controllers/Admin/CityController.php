<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\City;

class CityController extends CrudController {

    protected array $counts = ['areas'];

    protected string $model = City::class;

}
