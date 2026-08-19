<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_city', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('title');
            $table->ranking();
            $table->auditings();
        });

        Schema::create('base_city_area', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('city_id');
            $table->text('title');
            $table->text('post_code');
            $table->ranking();
            $table->auditings();
        });

        Schema::create('base_menu', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('parent_id')->nullable();
            $table->text('title')->nullable();
            $table->jsonb('data')->nullable();
            $table->schedules();
            $table->ranking();
            $table->auditings();
        });

        DB::statement('CREATE OR REPLACE VIEW base_operator AS
            SELECT id, \'User\' AS type, username FROM base_user
            UNION ALL
            SELECT id, \'Member\' AS type, username FROM base_member
            UNION ALL
            SELECT id, \'Vendor\' AS type, username FROM base_vendor');
    }

    public function down(): void {
        DB::statement('DROP VIEW IF EXISTS base_operator');

        Schema::dropIfExists('base_menu');
        Schema::dropIfExists('base_city_area');
        Schema::dropIfExists('base_city');
    }

};
