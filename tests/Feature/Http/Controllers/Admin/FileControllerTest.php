<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\File;
use MatrixPlatform\Services\FileService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class FileControllerTest extends FeatureTestCase {

    private const REGULAR = 5000;

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function stored(int $privilege = File::PUBLIC): File {
        return app(FileService::class)->upload(UploadedFile::fake()->createWithContent('original.bin', 'stored-bytes'), $privilege);
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $token, string $uri, array $input = []): TestResponse {
        return $this->withToken($token)->postJson($uri, $input);
    }

    private function token(): string {
        return UserFactory::new()->createOne(['id' => self::REGULAR])->createToken();
    }

    public function test_uploading_without_a_token_is_refused(): void {
        $this->postJson('admin/file/upload', ['privilege' => File::PUBLIC])->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_downloading_without_a_token_is_refused(): void {
        $this->postJson('admin/file/download', ['path' => 'nowhere/missing.bin'])->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_updating_without_a_token_is_refused(): void {
        $this->postJson('admin/file/update', ['path' => 'nowhere/missing.bin', 'name' => 'x'])->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_an_upload_returns_the_stored_path(): void {
        $response = $this->withToken($this->token())->post('admin/file/upload', [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'privilege' => File::PUBLIC
        ]);

        $response->assertJsonPath('success', true);

        $path = $response->json('data.path');

        $this->assertIsString($path);
        Storage::disk('public')->assertExists("files/{$path}");
    }

    public function test_an_upload_without_a_file_is_rejected_on_that_field(): void {
        $response = $this->send($this->token(), 'admin/file/upload', ['privilege' => File::PUBLIC]);

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
        $response->assertJsonPath('fields.file.0', 'required');
    }

    public function test_a_download_streams_the_stored_bytes(): void {
        $file = $this->stored(File::PRIVATE);

        $response = $this->withToken($this->token())->post('admin/file/download', ['path' => $file->path]);

        $response->assertOk();
        $this->assertSame('stored-bytes', $response->streamedContent());
        $this->assertStringContainsString('original.bin', strval($response->headers->get('content-disposition')));
    }

    public function test_a_download_carries_the_stored_mime_type(): void {
        $file = app(FileService::class)->upload(UploadedFile::fake()->createWithContent('report.csv', 'a,b'));

        $response = $this->withToken($this->token())->post('admin/file/download', ['path' => $file->path]);

        $response->assertOk();
        $this->assertSame('text/csv', $file->mime_type);
        $this->assertStringStartsWith('text/csv', strval($response->headers->get('content-type')));
    }

    public function test_downloading_an_unknown_path_is_reported_as_missing_data(): void {
        $this->send($this->token(), 'admin/file/download', ['path' => 'nowhere/missing.bin'])->assertJson(['code' => 404, 'error' => 'data-not-found']);
    }

    public function test_an_update_renames_the_file(): void {
        $file = $this->stored();

        $response = $this->send($this->token(), 'admin/file/update', [
            'path' => $file->path,
            'name' => 'renamed.bin',
            'description' => 'note'
        ]);

        $response->assertExactJson(['success' => true]);

        $file->refresh();

        $this->assertSame('renamed.bin', $file->name);
        $this->assertSame('note', $file->description);
    }

    public function test_an_account_without_any_permission_reaches_every_file_endpoint(): void {
        $token = $this->token();
        $file = $this->stored();

        $upload = $this->withToken($token)->post('admin/file/upload', [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'privilege' => File::PUBLIC
        ]);

        $upload->assertJsonPath('success', true);

        $this->withToken($token)
            ->post('admin/file/download', ['path' => $file->path])
            ->assertOk();

        $this->send($token, 'admin/file/update', ['path' => $file->path, 'name' => 'renamed.bin'])->assertJsonPath('success', true);
    }

    public function test_the_same_account_is_still_refused_by_a_guarded_endpoint(): void {
        $this->send($this->token(), 'admin/user')->assertJson(['code' => 403, 'error' => 'permission-denied']);
    }

}
