<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\Group;

class GroupController extends Controller {

    protected $model = Group::class;

    protected $sorting = ['title'];
    protected $updates = ['*title'];

}
