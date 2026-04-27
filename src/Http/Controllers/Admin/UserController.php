<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\User;

class UserController extends Controller {

    protected $model = User::class;

    protected $lists = ['username', 'group.title', 'disabled:boolean', 'enable_time', 'disable_time'];
    protected $sorting = ['username'];
    protected $updates = ['*username', 'password', 'group_id', 'disabled:boolean', 'enable_time', 'disable_time'];

    protected function onDelete($service) {
        return $this->applyHideRoot(parent::onDelete($service))->guard(fn ($model) => $model->id === user()->id && error('permission-denied'));
    }

    protected function onGet($service) {
        return $this->applyHideRoot(parent::onGet($service));
    }

    protected function onList($service) {
        return $this->applyHideRoot(parent::onList($service));
    }

    protected function onUpdate($service) {
        return $this->applyHideRoot(parent::onUpdate($service));
    }

    private function applyHideRoot($service) {
        return $service->when(user()->id !== User::ROOT, fn ($query) => $query->where("{$query->getModel()->getTable()}.id", '!=', User::ROOT));
    }

}
