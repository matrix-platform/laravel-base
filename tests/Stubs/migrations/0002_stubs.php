<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('stub_widget', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('title')->nullable();
            $table->text('secret')->nullable();
            $table->text('ip')->nullable();
            $table->ranking();
            $table->schedules();
            $table->auditings();
        });

        Schema::create('stub_trinket', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('label');
            $table->integer('widget_id')->nullable();
            $table->integer('trinket_id')->nullable();
            $table->integer('gadget_id')->nullable();
            $table->integer('amount')->nullable();
            $table->integer('owner_id')->nullable();
            $table->text('owner_type')->nullable();
            $table->ranking();
            $table->auditings();
        });

        Schema::create('stub_trinket_widget', function (BaseBlueprint $table) {
            $table->integer('trinket_id');
            $table->integer('widget_id');
        });

        Schema::create('stub_gadget', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('title')->nullable();
            $table->auditings();
        });
    }

    public function down(): void {
        Schema::dropIfExists('stub_gadget');
        Schema::dropIfExists('stub_trinket_widget');
        Schema::dropIfExists('stub_trinket');
        Schema::dropIfExists('stub_widget');
    }

};
