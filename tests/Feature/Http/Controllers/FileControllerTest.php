<?php //>

namespace Tests\Feature\Http\Controllers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\DriveService;
use MatrixPlatform\Services\FileService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class FileControllerTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * @return array{0: File, 1: DriveNode}
     */
    private function driveLinked(?UploadedFile $upload = null): array {
        $node = app(DriveService::class)->upload(DriveNode::query()->findOrFail(DriveNode::ROOT), $upload === null ? UploadedFile::fake()->createWithContent('cover.bin', 'drive-bytes') : $upload, $this->user());

        if ($node->size === null || $node->hash === null) {
            $this->fail('expected the uploaded drive node to have a size and hash');
        }

        $file = new File();

        $file->name = $node->name;
        $file->path = File::DRIVE_PREFIX . $node->path;
        $file->size = $node->size;
        $file->hash = $node->hash;
        $file->mime_type = $node->mime_type;
        $file->privilege = File::PRIVATE;

        $file->save();

        return [$file, $node];
    }

    private function stored(int $privilege, ?UploadedFile $upload = null): File {
        return app(FileService::class)->upload($upload === null ? UploadedFile::fake()->createWithContent('regular.bin', 'regular-bytes') : $upload, $privilege);
    }

    private function user(): User {
        return UserFactory::new()->createOne();
    }

    public function test_a_public_uploaded_file_redirects_to_its_public_url(): void {
        $file = $this->stored(File::PUBLIC);
        $location = app(FileService::class)->location($file);

        $response = $this->get("api/files/{$file->path}");

        $response->assertRedirect(Storage::disk('public')->url($location));
        Storage::disk('public')->assertExists($location);
    }

    public function test_a_public_uploaded_image_with_a_size_parameter_redirects_to_its_thumbnail(): void {
        $file = $this->stored(File::PUBLIC, UploadedFile::fake()->image('photo.png', 200, 100));
        $thumbnailLocation = app(FileService::class)->thumbnailLocation($file, 'icon');

        $response = $this->get("api/files/{$file->path}?size=icon");

        $response->assertRedirect(Storage::disk('public')->url($thumbnailLocation));
        Storage::disk('public')->assertExists($thumbnailLocation);
    }

    public function test_a_private_uploaded_file_is_refused_with_not_found(): void {
        $file = $this->stored(File::PRIVATE);

        $this->get("api/files/{$file->path}")->assertJson(['code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_drive_linked_file_streams_its_content(): void {
        [$file] = $this->driveLinked();

        $response = $this->get("api/files/{$file->path}");

        $response->assertOk();
        $this->assertSame('drive-bytes', $response->streamedContent());
    }

    public function test_a_drive_linked_file_still_streams_after_its_node_is_trashed(): void {
        [$file, $node] = $this->driveLinked();

        app(DriveService::class)->trash($node, $this->user());

        $response = $this->get("api/files/{$file->path}");

        $response->assertOk();
        $this->assertSame('drive-bytes', $response->streamedContent());
    }

    public function test_a_drive_linked_image_with_a_size_parameter_streams_a_webp_thumbnail(): void {
        [$file] = $this->driveLinked(UploadedFile::fake()->image('cover.png', 200, 100));

        $response = $this->get("api/files/{$file->path}?size=icon");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/webp');
        $this->assertSame('RIFF', substr($response->streamedContent(), 0, 4));
    }

    public function test_an_unknown_path_is_not_found(): void {
        $this->get('api/files/nowhere/missing.bin')->assertJson(['code' => 404, 'error' => 'data-not-found']);
    }

}
