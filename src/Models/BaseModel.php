<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use MatrixPlatform\Models\Builders\BaseBuilder;
use MatrixPlatform\Models\Generators\Creator;
use MatrixPlatform\Models\Generators\Updater;

abstract class BaseModel extends Model {

    const CREATED_AT = 'create_time';
    const CREATED_BY = 'creator_id';
    const UPDATED_AT = 'update_time';
    const UPDATED_BY = 'updater_id';

    const TRACEABLE = true;

    protected static function booted() {
        static::creating(fn ($model) => $model->applyCreatingGenerators());
        static::updating(fn ($model) => $model->applyUpdatingGenerators());

        if (static::TRACEABLE) {
            static::created(fn ($model) => $model->traceCreated());
            static::deleted(fn ($model) => $model->traceDeleted());
            static::updated(fn ($model) => $model->traceUpdated());
        }
    }

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

    protected function serializeDate($date) {
        return $date->format('Y-m-d H:i:s');
    }

    private function applyCreatingGenerators() {
        if (static::CREATED_BY) {
            $this->setAttribute(static::CREATED_BY, app(Creator::class)->generate());
        }

        if (static::UPDATED_AT) {
            $this->setAttribute(static::UPDATED_AT, null);
        }

        $this->applyGenerators('generate');
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

    private function applyUpdatingGenerators() {
        if (static::UPDATED_BY) {
            $this->setAttribute(static::UPDATED_BY, app(Updater::class)->regenerate());
        }

        $this->applyGenerators('regenerate');
    }

    private function getTraceables($data, $truncate = true) {
        if (property_exists($this, 'untraceable')) {
            Arr::forget($data, $this->untraceable);
        }

        Arr::forget($data, [$this->primaryKey, static::CREATED_AT, static::CREATED_BY, static::UPDATED_AT, static::UPDATED_BY]);

        return $truncate ? Arr::whereNotNull($data) : $data;
    }

    private function trace($type, $before, $after) {
        $log = new ManipulationLog;
        $log->type = $type;
        $log->data_type = $this->getTable();
        $log->data_id = $this->getKey();
        $log->before = $before === null || $before ? $before : (object) [];
        $log->after = $after === null || $after ? $after : (object) [];
        $log->save();
    }

    private function traceCreated() {
        $this->trace(1, null, $this->getTraceables($this->getAttributes()));
    }

    private function traceDeleted() {
        $this->trace(3, $this->getTraceables($this->getOriginal()), null);
    }

    private function traceUpdated() {
        $changes = $this->getTraceables($this->getChanges(), false);

        if ($changes) {
            $this->trace(2, Arr::map($changes, fn ($_, $name) => $this->getOriginal($name)), $changes);
        }
    }

}
