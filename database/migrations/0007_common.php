<?php //>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;

return new class extends Migration {

    public function up(): void {
        Schema::create('base_city', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->translatable('title');
            $table->ranking();
            $table->auditings();
        });

        Schema::create('base_city_area', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('city_id');
            $table->translatable('title');
            $table->text('post_code');
            $table->ranking();
            $table->auditings();

            $table->foreign('city_id')->references('id')->on('base_city');
        });

        Schema::create('base_menu', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('parent_id')->nullable();
            $table->translatable('title');
            $table->jsonb('data')->nullable();
            $table->schedules();
            $table->ranking();
            $table->auditings();
        });

        Schema::table('base_menu', function (BaseBlueprint $table) {
            $table->foreign('parent_id')->references('id')->on('base_menu');
        });

        Schema::create('base_page', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->text('path')->unique();
            $table->text('title');
            $table->translatable('seo_title');
            $table->translatable('seo_description');
            $table->translatable('og_image', 'jsonb');
            $table->jsonb('data')->nullable();
            $table->schedules();
            $table->ranking();
            $table->auditings();
        });

        Schema::create('base_block', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('page_id');
            $table->text('type');
            $table->text('title');
            $table->jsonb('data')->nullable();
            $table->schedules();
            $table->ranking();
            $table->auditings();

            $table->foreign('page_id')->references('id')->on('base_page');
        });

        Schema::create('base_block_item', function (BaseBlueprint $table) {
            $table->primaryKey();
            $table->integer('block_id');
            $table->text('title');
            $table->jsonb('data')->nullable();
            $table->schedules();
            $table->ranking();
            $table->auditings();

            $table->foreign('block_id')->references('id')->on('base_block');
        });

        DB::statement('CREATE OR REPLACE VIEW base_operator AS
            SELECT id, \'User\' AS type, username FROM base_user
            UNION ALL
            SELECT id, \'Member\' AS type, username FROM base_member
            UNION ALL
            SELECT id, \'Vendor\' AS type, username FROM base_vendor');
    }

    public function down(): void {
        DB::statement('DROP VIEW IF EXISTS base_operator');

        Schema::dropIfExists('base_block_item');
        Schema::dropIfExists('base_block');
        Schema::dropIfExists('base_page');
        Schema::dropIfExists('base_menu');
        Schema::dropIfExists('base_city_area');
        Schema::dropIfExists('base_city');
    }

};
