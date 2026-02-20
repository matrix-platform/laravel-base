<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('base_manipulation_log', function ($table) {
            $table->id();
            $table->integer('type');
            $table->timestamp('log_time')->useCurrent();
            $table->text('controller')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('member_id')->nullable();
            $table->text('ip')->nullable();
            $table->text('data_type');
            $table->integer('data_id');
            $table->jsonb('previous')->nullable();
            $table->jsonb('current')->nullable();
        });
    }

    public function down() {
        Schema::dropIfExists('base_manipulation_log');
    }

};
