<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_member', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('username')->unique();
            $table->text('password')->nullable();
            $table->text('name')->nullable();
            $table->text('mobile')->nullable();
            $table->text('mail')->nullable();
            $table->text('avatar')->nullable();
            $table->integer('status');
            $table->auditings();
        });

        Schema::create('base_member_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('member_id');
            $table->text('type');
            $table->jsonb('content')->nullable();
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->auditings(false);

            $table->foreign('member_id')->references('id')->on('base_member');
        });

        Schema::create('base_vendor', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('username')->unique();
            $table->text('password')->nullable();
            $table->text('title')->nullable();
            $table->text('tax_id')->nullable();
            $table->text('contact')->nullable();
            $table->text('mobile')->nullable();
            $table->text('mail')->nullable();
            $table->integer('status');
            $table->auditings();
        });

        Schema::create('base_vendor_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('vendor_id');
            $table->text('type');
            $table->jsonb('content')->nullable();
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->auditings(false);

            $table->foreign('vendor_id')->references('id')->on('base_vendor');
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_vendor_log');
        Schema::dropIfExists('base_vendor');
        Schema::dropIfExists('base_member_log');
        Schema::dropIfExists('base_member');
    }

};
