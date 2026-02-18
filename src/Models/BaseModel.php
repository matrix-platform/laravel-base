<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\Builders\BaseBuilder;

abstract class BaseModel extends Model {

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected static function booted() {
        static::creating(fn ($model) => $model->applyGenerators('generate'));
        static::updating(fn ($model) => $model->applyGenerators('regenerate'));
    }

    public function newEloquentBuilder($query) {
        return new BaseBuilder($query);
    }

    protected function serializeDate($date) {
        return $date->format('Y-m-d H:i:s');
    }

    private function applyGenerators($type) {
        if (property_exists($this, 'generators')) {
            foreach ($this->generators as $name => $class) {
                if (method_exists($class, $type)) {
                    $this->{$name} = app($class)->{$type}($this->{$name}, $this);
                }
            }
        }
    }

}
