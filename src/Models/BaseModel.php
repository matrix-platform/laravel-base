<?php //>

namespace MatrixPlatform\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MatrixPlatform\Models\Builders\BaseBuilder;
use MatrixPlatform\Models\Generators\Creator;
use MatrixPlatform\Models\Generators\Generates;
use MatrixPlatform\Models\Generators\Regenerates;
use MatrixPlatform\Models\Generators\Updater;

abstract class BaseModel extends Model {

    const CREATED_AT = 'create_time';
    const CREATED_BY = 'creator_id';
    const TRACEABLE = true;
    const UPDATED_AT = 'update_time';
    const UPDATED_BY = 'updater_id';

    protected static function booted(): void {
        static::creating(fn (BaseModel $model) => $model->applyCreatingGenerators());
        static::updating(fn (BaseModel $model) => $model->applyUpdatingGenerators());

        if (static::TRACEABLE) {
            static::created(fn (BaseModel $model) => $model->traceCreated());
            static::deleted(fn (BaseModel $model) => $model->traceDeleted());
            static::updated(fn (BaseModel $model) => $model->traceUpdated());
        }
    }

    /**
     * @var array<string, class-string>
     */
    protected array $generators = [];

    /**
     * @var list<string>
     */
    protected array $untraceable = [];

    public function lock(): static {
        $data = static::newQueryWithoutScopes()
            ->whereKey($this->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $latest = $data->getOriginal();
        $reserved = $this->reserved();

        foreach ($this->getOriginal() as $key => $value) {
            if (!in_array($key, $reserved, true) && array_get_value($latest, $key) != $value) {
                error('data-conflicted');
            }
        }

        return $this;
    }

    /**
     * @return BaseBuilder<*>
     */
    public function newEloquentBuilder($query): BaseBuilder {
        return new BaseBuilder($query);
    }

    protected function serializeDate(DateTimeInterface $date): string {
        return $date->format('Y-m-d H:i:s');
    }

    private function applyCreatingGenerators(): void {
        if (static::CREATED_BY !== null) {
            $this->setAttribute(static::CREATED_BY, app(Creator::class)->generate(null, $this));
        }

        if (static::UPDATED_AT !== null) {
            $this->setAttribute(static::UPDATED_AT, null);
        }

        foreach ($this->generators as $name => $class) {
            if (is_a($class, Generates::class, true)) {
                $this->setAttribute($name, app($class)->generate($this->getAttribute($name), $this));
            }
        }
    }

    private function applyUpdatingGenerators(): void {
        if (static::UPDATED_BY !== null) {
            $this->setAttribute(static::UPDATED_BY, app(Updater::class)->regenerate(null, $this));
        }

        foreach ($this->generators as $name => $class) {
            if (is_a($class, Regenerates::class, true)) {
                $this->setAttribute($name, app($class)->regenerate($this->getAttribute($name), $this));
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function getTraceables(array $data, bool $truncate = true): array {
        Arr::forget($data, $this->untraceable);
        Arr::forget($data, $this->reserved());

        return $truncate ? Arr::whereNotNull($data) : $data;
    }

    /**
     * @return list<string>
     */
    private function reserved(): array {
        return array_values(Arr::whereNotNull([
            $this->getKeyName(),
            static::CREATED_AT,
            static::CREATED_BY,
            static::UPDATED_AT,
            static::UPDATED_BY
        ]));
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function trace(ManipulationType $type, ?array $before, ?array $after): void {
        $log = new ManipulationLog();

        $log->type = $type;
        $log->data_type = $this->getTable();
        $log->data_id = (int) $this->getKey();
        $log->before = $before === [] ? (object) [] : $before;
        $log->after = $after === [] ? (object) [] : $after;

        $log->save();
    }

    private function traceCreated(): void {
        $this->trace(ManipulationType::Created, null, $this->getTraceables($this->getAttributes()));
    }

    private function traceDeleted(): void {
        $this->trace(ManipulationType::Deleted, $this->getTraceables($this->getOriginal()), null);
    }

    private function traceUpdated(): void {
        $changes = $this->getTraceables($this->getChanges(), false);

        if ($changes) {
            $original = $this->getOriginal();

            $this->trace(ManipulationType::Updated, Arr::map($changes, fn (mixed $_, string $name) => array_get_value($original, $name)), $changes);
        }
    }

}
