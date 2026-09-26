<?php //>

namespace MatrixPlatform\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

enum ScheduleFilter: string {

    public const NAME = 'schedule';

    case Enabled = 'enabled';
    case Disabling = 'disabling';
    case Disabled = 'disabled';
    case Enabling = 'enabling';
    case Expired = 'expired';

    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     */
    public function apply(Builder $query, string $enable, string $disable, CarbonInterface $now): void {
        $e = $query->qualifyColumn($enable);
        $d = $query->qualifyColumn($disable);

        $query->where(function (Builder $query) use ($e, $d, $now): void {
            match ($this) {
                self::Enabled => $query
                    ->where($e, '<=', $now)
                    ->where(fn (Builder $query) => $query->whereNull($d)->orWhere($d, '>', $now)),
                self::Disabling => $query->where($e, '<=', $now)->where($d, '>', $now),
                self::Disabled => $query
                    ->whereNull($e)
                    ->orWhere($e, '>', $now)
                    ->orWhere($d, '<=', $now),
                self::Enabling => $query->where($e, '>', $now),
                self::Expired => $query->where($d, '<=', $now)
            };
        });
    }

}
