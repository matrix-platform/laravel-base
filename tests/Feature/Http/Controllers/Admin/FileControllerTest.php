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

    private function stored(): File {
        return app(FileService::class)->upload(UploadedFile::fake()->createWithContent('original.bin', 'stored-bytes'));
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

    public function test_updating_without_a_token_is_refused(): void {
        $this->postJson('admin/file/update', ['path' => 'nowhere/missing.bin', 'name' => 'x'])->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_an_upload_returns_the_stored_path(): void {
        $response = $this->withToken($this->token())->post('admin/file/upload', [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'privilege' => File::PUBLIC
        ]);

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'photo.png');
        $response->assertJsonPath('data.mime_type', 'image/png');
        $response->assertJsonPath('data.width', 10);
        $response->assertJsonPath('data.height', 10);
        $response->assertJsonPath('data.seconds', null);

        $this->assertIsInt($response->json('data.size'));
        $this->assertGreaterThan(0, $response->json('data.size'));

        $path = $response->json('data.path');

        $this->assertIsString($path);
        Storage::disk('public')->assertExists("files/{$path}");
    }

    public function test_an_upload_records_the_usage_it_was_given(): void {
        $response = $this->withToken($this->token())->post('admin/file/upload', [
            'file' => UploadedFile::fake()->createWithContent('avatar.bin', 'avatar-bytes'),
            'privilege' => File::PRIVATE,
            'usage' => 'avatar'
        ]);

        $file = File::query()->where('path', $response->json('data.path'))->sole();

        $this->assertSame('avatar', $file->usage);
        $this->assertSame(File::PRIVATE, $file->privilege);
    }

    public function test_an_upload_with_a_privilege_outside_the_enum_is_rejected(): void {
        $response = $this->send($this->token(), 'admin/file/upload', [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'privilege' => 99
        ]);

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
        $response->assertJsonPath('fields.privilege.0', 'in');

        $this->assertSame(0, File::query()->count());
    }

    public function test_an_upload_without_a_file_is_rejected_on_that_field(): void {
        $response = $this->send($this->token(), 'admin/file/upload', ['privilege' => File::PUBLIC]);

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
        $response->assertJsonPath('fields.file.0', 'required');
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

    public function test_updating_an_unknown_path_is_not_found(): void {
        $this->send($this->token(), 'admin/file/update', ['path' => 'nowhere/missing.bin', 'name' => 'x'])
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_the_same_account_is_still_refused_by_a_guarded_endpoint(): void {
        $this->send($this->token(), 'admin/user')->assertJson(['code' => 403, 'error' => 'permission-denied']);
    }

}
