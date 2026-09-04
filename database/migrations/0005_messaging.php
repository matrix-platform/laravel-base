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

        Schema::create('base_push_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('provider');
            $table->integer('member_id');
            $table->text('title')->nullable();
            $table->text('content');
            $table->jsonb('data')->nullable();
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

        Schema::create('base_push_subscription', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('member_id');
            $table->text('endpoint')->unique();
            $table->text('p256dh');
            $table->text('auth');
            $table->text('user_agent')->nullable();
            $table->text('ip')->nullable();
            $table->auditings();

            $table->index('member_id');
        });

        Schema::create('base_telegram_log', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('provider');
            $table->text('chat_id');
            $table->text('content');
            $table->jsonb('data')->nullable();
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

        Schema::create('base_telegram_subscription', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('user_id')->unique();
            $table->text('chat_id')->unique();
            $table->text('username')->nullable();
            $table->auditings();
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_telegram_subscription');
        Schema::dropIfExists('base_telegram_log');
        Schema::dropIfExists('base_push_subscription');
        Schema::dropIfExists('base_push_log');
        Schema::dropIfExists('base_sms_log');
        Schema::dropIfExists('base_mail_log');
    }

};
