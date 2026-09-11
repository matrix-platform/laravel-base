<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('scaffold_parent', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->translatable('title');
            $table->ranking();
            $table->auditings();
        });

        Schema::create('scaffold_simple', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->translatable('title');
            $table->schedules();
            $table->ranking();
            $table->auditings();
        });

        Schema::create('scaffold_widget', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('parent_id')->nullable();
            $table->text('code');
            $table->text('password')->nullable();
            $table->text('api_token')->nullable();
            $table->text('secret_key')->nullable();
            $table->float('weight')
                ->nullable()
                ->comment('Weight in kilograms');
            $table->translatable('title');
            $table->schedules();
            $table->ranking();
            $table->auditings();
            $table->unique('code');
            $table->unique(['weight', 'parent_id']);
            $table->foreign('parent_id')
                ->references('id')
                ->on('scaffold_parent');
        });
    }

    public function down(): void {
        Schema::dropIfExists('scaffold_widget');
        Schema::dropIfExists('scaffold_simple');
        Schema::dropIfExists('scaffold_parent');
    }

};
