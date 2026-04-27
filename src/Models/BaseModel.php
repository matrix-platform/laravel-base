<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MatrixPlatform\Models\Builders\BaseBuilder;

abstract class BaseModel extends Model {

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $alias = null;
    protected $parent = null;
    protected $title = 'title';
    protected $types = [];

    public function getAlias() {
        return $this->alias ?: Str::kebab(class_basename($this));
    }

    public function getParent() {
        return $this->parent;
    }

    public function getType($column) {
        return data_get($this->types, $column) ?: data_get($this->casts, $column, 'text');
    }

    public function lock() {
        $data = static::newQueryWithoutScopes()->lockForUpdate()->findOrFail($this->getKey());

        if ($this->getRawOriginal() != $data->getRawOriginal()) {
            error('data-conflicted');
        }

        return $this;
    }

    public function newEloquentBuilder($query) {
        return new BaseBuilder($query);
    }

    public function toTitle() {
        return $this->getAttribute($this->title);
    }

    protected static function booted() {
        static::creating(fn ($model) => $model->applyGenerators('generate'));
        static::updating(fn ($model) => $model->applyGenerators('regenerate'));
    }

    protected function serializeDate($date) {
        return $date->format('Y-m-d H:i:s');
    }

    private function applyGenerators($type) {
        if (property_exists($this, 'generators')) {
            foreach ($this->generators as $name => $class) {
                if (method_exists($class, $type)) {
                    $this->setAttribute($name, app($class)->{$type}($this->getAttribute($name), $this));
                }
            }
        }
    }

}
