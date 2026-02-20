<?php //>

namespace MatrixPlatform\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

trait Traceable {

    public static function bootTraceable() {
        static::created(fn ($model) => $model->traceCreated());
        static::deleted(fn ($model) => $model->traceDeleted());
        static::updated(fn ($model) => $model->traceUpdated());
    }

    private function encodeTraceables($data) {
        return $data === null ? null : json_encode($data, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
    }

    private function getTraceables($data, $truncate = true) {
        if (property_exists($this, 'untraceable')) {
            Arr::forget($data, $this->untraceable);
        }

        Arr::forget($data, ['id', 'created_at', 'updated_at']);

        return $truncate ? Arr::whereNotNull($data) : $data;
    }

    private function trace($type, $previous, $current) {
        DB::table('base_manipulation_log')->insert([
            'type' => $type,
            'controller' => Request::route()?->uri(),
            'user_id' => defined('USER_ID') ? USER_ID : null,
            'member_id' => defined('MEMBER_ID') ? MEMBER_ID : null,
            'ip' => Request::ip(),
            'data_type' => $this->getTable(),
            'data_id' => $this->getKey(),
            'previous' => $this->encodeTraceables($previous),
            'current' => $this->encodeTraceables($current),
        ]);
    }

    private function traceCreated() {
        $this->trace(1, null, $this->getTraceables($this->getAttributes()));
    }

    private function traceDeleted() {
        $this->trace(3, $this->getTraceables($this->getOriginal()), null);
    }

    private function traceUpdated() {
        $current = $this->getTraceables($this->getChanges(), false);
        $previous = Arr::map($current, fn ($_, $name) => $this->getOriginal($name));

        $this->trace(2, $previous, $current);
    }

}
