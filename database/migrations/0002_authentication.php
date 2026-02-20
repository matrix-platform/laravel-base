<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        DB::statement('CREATE SEQUENCE base_id START WITH 10000000');

        Schema::create('base_auth_token', function ($table) {
            $table->primaryKey();
            $table->text('token')->unique();
            $table->integer('type');
            $table->integer('target_id');
            $table->text('user_agent')->nullable();
            $table->text('ip');
            $table->timestamp('modify_time');
            $table->timestamp('create_time');
            $table->timestamp('expire_time')->nullable();
        });

        Schema::create('base_group', function ($table) {
            $table->primaryKey();
            $table->text('title')->unique();
        });

        Schema::create('base_user', function ($table) {
            $table->primaryKey();
            $table->text('username')->unique();
            $table->text('password')->nullable();
            $table->integer('group_id')->nullable();
            $table->schedules();
            $table->boolean('disabled');
        });

        Schema::create('base_user_log', function ($table) {
            $table->primaryKey();
            $table->integer('user_id');
            $table->integer('type');
            $table->text('ip');
            $table->timestamp('create_time');
        });
    }

    public function down() {
        Schema::dropIfExists('base_user_log');
        Schema::dropIfExists('base_user');
        Schema::dropIfExists('base_group');
        Schema::dropIfExists('base_auth_token');

        DB::statement('DROP SEQUENCE IF EXISTS base_id');
    }

};
