<?php //>

namespace MatrixPlatform\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class BaseBlueprint extends Blueprint {

    public function auditings(bool $updated = true): void {
        $this->integer('creator_id')->nullable();
        $this->timestamp('create_time');

        if ($updated) {
            $this->integer('updater_id')->nullable();
            $this->timestamp('update_time')->nullable();
        }
    }

    public function primaryKey(): void {
        $this->integer('id')->default(DB::raw('NEXTVAL(\'base_id\')'))->primary();
    }

    public function ranking(): void {
        $this->integer('ranking')->default(DB::raw('NEXTVAL(\'base_ranking\')'));
    }

    public function schedules(): void {
        $this->timestamp('enable_time')->nullable();
        $this->timestamp('disable_time')->nullable();
    }

}
