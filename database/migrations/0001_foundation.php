<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS base_id START WITH 10000000');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS base_ranking START WITH 100 INCREMENT BY 100');

        Schema::create('base_manipulation_log', function (BaseBlueprint $table) {
            $table->id();
            $table->integer('type');
            $table->text('endpoint')->nullable();
            $table->text('ip')->nullable();
            $table->text('data_type');
            $table->integer('data_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->auditings(false);
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_manipulation_log');

        DB::statement('DROP SEQUENCE IF EXISTS base_ranking');
        DB::statement('DROP SEQUENCE IF EXISTS base_id');
    }

};
