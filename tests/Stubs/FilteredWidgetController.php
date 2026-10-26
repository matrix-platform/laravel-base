<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Builder;

class FilteredWidgetController extends WidgetController {

    protected function filters(): array {
        return ['note' => fn (Builder $query, array $filter) => $query->where('title', $filter['value'])];
    }

    protected function listing(): array {
        return [...parent::listing(), ['name' => '+note', 'type' => 'text', 'op' => 'contains']];
    }

}
