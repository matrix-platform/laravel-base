<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
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
    }

};
