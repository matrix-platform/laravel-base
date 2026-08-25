<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_auth_token', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('token')->unique();
            $table->text('type');
            $table->integer('target_id');
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expire_time')->nullable();
            $table->auditings();
        });

        Schema::create('base_group', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('title')->unique();
            $table->jsonb('permissions')->nullable();
            $table->auditings();
        });

        Schema::create('base_user', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('username')->unique();
            $table->text('password')->nullable();
            $table->integer('group_id')->nullable();
            $table->jsonb('permissions')->nullable();
            $table->schedules();
            $table->boolean('disabled');
            $table->auditings();

            $table->foreign('group_id')->references('id')->on('base_group');
        });

        Schema::create('base_user_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('user_id');
            $table->text('type');
            $table->jsonb('content')->nullable();
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->auditings(false);

            $table->foreign('user_id')->references('id')->on('base_user');
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_user_log');
        Schema::dropIfExists('base_user');
        Schema::dropIfExists('base_group');
        Schema::dropIfExists('base_auth_token');
    }

};
