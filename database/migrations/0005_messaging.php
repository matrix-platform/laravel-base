<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_mail_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('provider');
            $table->text('sender');
            $table->text('receiver');
            $table->text('subject');
            $table->text('content');
            $table->text('template')->nullable();
            $table->timestamp('schedule_time');
            $table->timestamp('send_time')->nullable();
            $table->text('response')->nullable();
            $table->text('error')->nullable();
            $table->text('ip')->nullable();
            $table->text('locale');
            $table->integer('status');
            $table->auditings();
            $table->index(['status', 'schedule_time']);
        });

        Schema::create('base_sms_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('provider');
            $table->text('receiver');
            $table->text('content');
            $table->text('template')->nullable();
            $table->timestamp('schedule_time');
            $table->timestamp('send_time')->nullable();
            $table->text('response')->nullable();
            $table->text('error')->nullable();
            $table->text('ip')->nullable();
            $table->text('locale');
            $table->integer('status');
            $table->auditings();
            $table->index(['status', 'schedule_time']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_sms_log');
        Schema::dropIfExists('base_mail_log');
    }

};
