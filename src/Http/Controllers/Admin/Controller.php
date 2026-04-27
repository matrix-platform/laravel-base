<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\Common\DeleteService;
use MatrixPlatform\Services\Admin\Common\GetService;
use MatrixPlatform\Services\Admin\Common\InsertService;
use MatrixPlatform\Services\Admin\Common\ListService;
use MatrixPlatform\Services\Admin\Common\NewService;
use MatrixPlatform\Services\Admin\Common\UpdateService;

abstract class Controller extends BaseController {

    protected $model;
    protected $inserts;
    protected $lists;
    protected $sorting;
    protected $updates;

    #[Action]
    public function delete(Request $request) {
        return $this->onDelete(new DeleteService($this->model))
            ->params($request->route()->parameters())
            ->output($request->all());
    }

    #[Action('{id}')]
    public function get(Request $request) {
        return $this->onGet(new GetService($this->model))
            ->params($request->route()->parameters())
            ->output($request->route('id'));
    }

    #[Action]
    public function insert(Request $request) {
        return $this->onInsert(new InsertService($this->model))
            ->params($request->route()->parameters())
            ->output($request->all());
    }

    #[Action('')]
    public function list(Request $request) {
        return $this->onList(new ListService($this->model))
            ->params($request->route()->parameters())
            ->output($request->all());
    }

    #[Action]
    public function new(Request $request) {
        return $this->onNew(new NewService($this->model))
            ->params($request->route()->parameters())
            ->output();
    }

    #[Action('{id}/update')]
    public function update(Request $request) {
        return $this->onUpdate(new UpdateService($this->model))
            ->params($request->route()->parameters())
            ->output($request->route('id'), $request->all());
    }

    protected function onDelete($service) {
        return $service;
    }

    protected function onGet($service) {
        return $service->columns($this->updates);
    }

    protected function onInsert($service) {
        return $service->columns($this->inserts ?: $this->updates);
    }

    protected function onList($service) {
        return $service->columns($this->lists ?: $this->updates)->sorting($this->sorting ?: []);
    }

    protected function onNew($service) {
        return $service->columns($this->inserts ?: $this->updates);
    }

    protected function onUpdate($service) {
        return $service->columns($this->updates);
    }

}
