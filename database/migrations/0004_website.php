<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        DB::statement('CREATE SEQUENCE base_ranking START WITH 100 INCREMENT BY 100');

        Schema::create('base_city', function ($table) {
            $table->primaryKey();
            $table->text('title');
            $table->ranking();
        });

        Schema::create('base_city_area', function ($table) {
            $table->primaryKey();
            $table->integer('city_id');
            $table->text('title');
            $table->text('post_code');
            $table->ranking();
        });
    }

    public function down() {
        Schema::dropIfExists('base_city_area');
        Schema::dropIfExists('base_city');

        DB::statement('DROP SEQUENCE IF EXISTS base_ranking');
    }

};
