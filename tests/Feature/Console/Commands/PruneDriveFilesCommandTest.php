<?php //>

namespace Tests\Feature\Console\Commands;

use App\Models\DriveProbe;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Models\File;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;

class PruneDriveFilesCommandTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        require_once __DIR__ . '/../../../fixtures/package-drive/app/Models/DriveProbe.php';

        app(PackageRegistry::class)->register('drive-package', __DIR__ . '/../../../fixtures/package-drive');

        config(['matrix.packages' => 'drive-package']);
    }

    private function declared(): void {
        app(MetadataRegistry::class)->register(DriveProbe::class, new StubDeclaration(new Metadata('probe'), [
            'payload' => Definition::json(Presentation::DriveImage)
        ]));
    }

    private function linked(string $path): File {
        return File::forceCreate([
            'name' => 'linked.jpg',
            'path' => $path,
            'size' => 10,
            'hash' => "hash-{$path}",
            'mime_type' => 'image/jpeg',
            'privilege' => File::PRIVATE
        ]);
    }

    private function referencing(string $path): void {
        DriveProbe::forceCreate(['payload' => [['path' => $path, 'name' => 'linked.jpg']]]);
    }

    private function survived(string $path): bool {
        return File::query()->where('path', $path)->exists();
    }

    public function test_a_drive_linked_file_still_referenced_by_a_crud_record_is_kept(): void {
        $this->declared();
        $this->linked(File::DRIVE_PREFIX . 'a');
        $this->referencing(File::DRIVE_PREFIX . 'a');

        $this->artisanCommand('matrix:prune-drive-files')->assertExitCode(0);

        $this->assertTrue($this->survived(File::DRIVE_PREFIX . 'a'));
    }

    public function test_a_drive_linked_file_with_no_referencing_record_is_deleted_immediately(): void {
        $this->declared();
        $this->linked(File::DRIVE_PREFIX . 'b');

        $this->artisanCommand('matrix:prune-drive-files')->assertExitCode(0);

        $this->assertFalse($this->survived(File::DRIVE_PREFIX . 'b'));
    }

    public function test_a_regular_uploaded_file_is_never_touched(): void {
        $this->declared();
        $this->linked('2020/01/regular.bin');

        $this->artisanCommand('matrix:prune-drive-files')->assertExitCode(0);

        $this->assertTrue($this->survived('2020/01/regular.bin'));
    }

    public function test_a_field_only_declared_on_the_controller_and_not_in_the_model_declaration_is_invisible_to_the_scan(): void {
        $this->linked(File::DRIVE_PREFIX . 'd');
        $this->referencing(File::DRIVE_PREFIX . 'd');

        $this->artisanCommand('matrix:prune-drive-files')->assertExitCode(0);

        $this->assertFalse($this->survived(File::DRIVE_PREFIX . 'd'));
    }

}
