<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS base_ranking START WITH 100 INCREMENT BY 100');

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

        Schema::create('base_file', function ($table) {
            $table->primaryKey();
            $table->text('name');
            $table->text('path')->unique();
            $table->bigInteger('size');
            $table->text('hash');
            $table->text('description')->nullable();
            $table->text('mime_type')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('seconds')->nullable();
            $table->integer('privilege');
            $table->integer('user_id')->nullable();
            $table->integer('member_id')->nullable();
        });

        Schema::create('base_menu', function ($table) {
            $table->primaryKey();
            $table->integer('parent_id')->nullable();
            $table->text('title')->nullable();
            $table->jsonb('data')->nullable();
            $table->schedules();
            $table->ranking();
        });
    }

    public function down() {
        Schema::dropIfExists('base_menu');
        Schema::dropIfExists('base_file');
        Schema::dropIfExists('base_city_area');
        Schema::dropIfExists('base_city');

        DB::statement('DROP SEQUENCE IF EXISTS base_ranking');
    }

};
