<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_drive_node', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('parent_id')->nullable();
            $table->text('type');
            $table->text('name');
            $table->text('path')->nullable();
            $table->bigInteger('size')->nullable();
            $table->text('hash')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('mime_type')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('seconds')->nullable();
            $table->auditings();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::table('base_drive_node', function (BaseBlueprint $table) {
            $table->foreign('parent_id')->references('id')->on('base_drive_node');
        });

        DB::statement('CREATE UNIQUE INDEX base_drive_node_sibling_name ON base_drive_node (parent_id, name) WHERE deleted_at IS NULL');
        DB::statement("INSERT INTO base_drive_node (id, type, name, create_time) VALUES (0, 'root', 'root', NOW())");

        Schema::create('base_file', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('name');
            $table->text('path')->unique();
            $table->bigInteger('size');
            $table->text('hash')->index();
            $table->text('description')->nullable();
            $table->text('mime_type')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('seconds')->nullable();
            $table->integer('privilege');
            $table->text('usage')->nullable();
            $table->auditings();
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_file');
        Schema::dropIfExists('base_drive_node');
    }

};
