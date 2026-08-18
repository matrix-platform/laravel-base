<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_resource_override', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('bundle')->unique();
            $table->jsonb('data');
            $table->auditings();
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_resource_override');
    }

};
