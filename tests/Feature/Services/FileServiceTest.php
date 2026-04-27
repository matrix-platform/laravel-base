<?php //>

namespace Tests\Feature\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\File;
use MatrixPlatform\Services\FileService;
use MatrixPlatform\Support\RollbackCallbacks;
use Tests\FeatureTestCase;

class FileServiceTest extends FeatureTestCase {

    protected function defineEnvironment($app) {
        parent::defineEnvironment($app);
        $app['config']->set('matrix.file-private-disk', 'local');
        $app['config']->set('matrix.file-public-disk', 'public');
    }

    private function fakeFile($name = 'test.txt', $kb = 1) {
        return UploadedFile::fake()->create($name, $kb);
    }

    private function sharedFile($name = 'shared.txt') {
        $path = tempnam(sys_get_temp_dir(), 'fs_test_');
        file_put_contents($path, str_repeat('X', 1024));

        return new UploadedFile($path, $name, 'text/plain', null, true);
    }

    private function service() {
        return app(FileService::class);
    }

    public function test_upload_creates_db_record() {
        Storage::fake('public');

        $this->service()->upload($this->fakeFile());

        $this->assertDatabaseHas('base_file', ['name' => 'test.txt']);
    }

    public function test_upload_stores_file_on_disk() {
        Storage::fake('public');

        $this->service()->upload($this->fakeFile());

        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_upload_same_content_and_privilege_returns_existing_record() {
        Storage::fake('public');
        $file1 = $this->sharedFile();
        $file2 = new UploadedFile($file1->getRealPath(), 'shared.txt', 'text/plain', null, true);

        $first = $this->service()->upload($file1);
        $second = $this->service()->upload($file2);

        $this->assertEquals($first->id, $second->id);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_upload_different_privilege_creates_new_record() {
        Storage::fake('public');
        Storage::fake('local');
        $file1 = $this->sharedFile();
        $file2 = new UploadedFile($file1->getRealPath(), 'shared.txt', 'text/plain', null, true);

        $public = $this->service()->upload($file1, 0);
        $private = $this->service()->upload($file2, 1);

        $this->assertNotEquals($public->id, $private->id);
    }

    public function test_upload_exceeds_max_size_throws() {
        Storage::fake('public');

        $this->expectException(ServiceException::class);
        $this->service()->upload($this->fakeFile('big.txt', 100), 0, 50 * 1024);
    }

    public function test_upload_allowed_mime_passes() {
        Storage::fake('public');

        $result = $this->service()->upload($this->fakeFile(), 0, 0, ['/^text\//']);

        $this->assertInstanceOf(File::class, $result);
    }

    public function test_upload_disallowed_mime_throws() {
        Storage::fake('public');

        $this->expectException(ServiceException::class);
        $this->service()->upload($this->fakeFile(), 0, 0, ['/^image\//']);
    }

    public function test_upload_empty_mimes_allows_any_type() {
        Storage::fake('public');

        $result = $this->service()->upload($this->fakeFile(), 0, 0, []);

        $this->assertInstanceOf(File::class, $result);
    }

    public function test_upload_rollback_callback_deletes_stored_file() {
        Storage::fake('public');

        $this->service()->upload($this->fakeFile());
        $this->assertCount(1, Storage::disk('public')->allFiles());

        app(RollbackCallbacks::class)->run();

        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_update_changes_name_and_description() {
        File::forceCreate([
            'description' => null,
            'hash' => str_repeat('a', 64),
            'mime_type' => null,
            'name' => 'original.txt',
            'path' => '202504/dummy.txt',
            'privilege' => 0,
            'size' => 100,
        ]);

        $result = $this->service()->update('202504/dummy.txt', 'renamed.txt', 'new desc');

        $this->assertEquals('renamed.txt', $result->name);
        $this->assertEquals('new desc', $result->description);
        $this->assertDatabaseHas('base_file', [
            'description' => 'new desc',
            'name' => 'renamed.txt',
            'path' => '202504/dummy.txt',
        ]);
    }

    public function test_update_throws_when_path_not_found() {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service()->update('no/such/path.txt', 'x.txt', null);
    }

}
