<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('base_manipulation_log', function ($table) {
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

    public function down() {
        Schema::dropIfExists('base_manipulation_log');
    }

};
