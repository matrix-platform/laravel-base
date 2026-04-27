<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('base_member', function ($table) {
            $table->primaryKey();
            $table->text('username')->unique();
            $table->text('password')->nullable();
            $table->text('name')->nullable();
            $table->text('mobile')->nullable();
            $table->text('mail')->nullable();
            $table->text('avatar')->nullable();
            $table->integer('status');
        });

        Schema::create('base_member_log', function ($table) {
            $table->primaryKey();
            $table->integer('member_id');
            $table->text('type');
            $table->jsonb('content')->nullable();
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('create_time');
        });

        Schema::create('base_sms_log', function ($table) {
            $table->primaryKey();
            $table->text('provider');
            $table->text('receiver');
            $table->text('type');
            $table->text('content');
            $table->text('response')->nullable();
            $table->text('ip')->nullable();
            $table->timestamp('send_time')->nullable();
            $table->integer('status');
            $table->timestamp('create_time');
        });

        Schema::create('base_mail_log', function ($table) {
            $table->primaryKey();
            $table->text('mailer');
            $table->text('sender');
            $table->text('receiver');
            $table->text('type');
            $table->text('subject');
            $table->text('content');
            $table->text('ip')->nullable();
            $table->timestamp('send_time')->nullable();
            $table->integer('status');
            $table->timestamp('create_time');
        });
    }

    public function down() {
        Schema::dropIfExists('base_mail_log');
        Schema::dropIfExists('base_sms_log');
        Schema::dropIfExists('base_member_log');
        Schema::dropIfExists('base_member');
    }

};
