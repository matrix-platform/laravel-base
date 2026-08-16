<?php //>

namespace Tests\Feature\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Services\FileService;
use Tests\FeatureTestCase;

class FileServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function audited(string $table): array {
        $log = ManipulationLog::query()
            ->where('data_type', $table)
            ->latest('id')
            ->firstOrFail();

        $after = $log->after;

        $this->assertNotNull($after);

        return $after;
    }

    private function blob(string $name, string $content): UploadedFile {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function refuses(string $slug, callable $upload): void {
        try {
            $upload();
        } catch (ServiceException $exception) {
            $this->assertSame($slug, $exception->getError());

            return;
        }

        $this->fail("expected the upload to be refused with '{$slug}'");
    }

    private function reload(File $file): File {
        return File::query()->whereKey($file->id)->firstOrFail();
    }

    private function rollback(callable $callback): void {
        try {
            DB::transaction(function () use ($callback): void {
                $callback();

                error('request-failed');
            });
        } catch (ServiceException $exception) {
            $this->assertSame('request-failed', $exception->getError());
        }
    }

    private function service(): FileService {
        return new FileService();
    }

    private function tone(): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'tone') . '.wav';

        copy(__DIR__ . '/../../fixtures/media/tone.wav', $path);

        return new UploadedFile($path, 'tone.wav', null, null, true);
    }

    public function test_an_image_upload_records_its_dimensions_on_the_public_disk(): void {
        $file = $this->service()->upload(UploadedFile::fake()->image('photo.png', 20, 10));

        $this->assertSame('photo.png', $file->name);
        $this->assertSame(File::PUBLIC, $file->privilege);
        $this->assertSame(20, $file->width);
        $this->assertSame(10, $file->height);

        Storage::disk('public')->assertExists("files/{$file->path}");
    }

    public function test_a_privileged_upload_lands_on_the_private_disk_only(): void {
        $file = $this->service()->upload($this->blob('secret.bin', 'top-secret'), File::PRIVATE);

        Storage::disk('local')->assertExists("files/{$file->path}");
        Storage::disk('public')->assertMissing("files/{$file->path}");
    }

    public function test_identical_bytes_are_stored_once(): void {
        $first = $this->service()->upload($this->blob('a.bin', 'same-content'));
        $second = $this->service()->upload($this->blob('b.bin', 'same-content'));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, File::query()->count());
        $this->assertCount(1, Storage::disk('public')->allFiles('files/' . date('Ym')));
    }

    public function test_identical_bytes_under_a_different_usage_are_stored_separately(): void {
        $kyc = $this->service()->upload($this->blob('a.bin', 'same-content'), File::PUBLIC, null, null, 'kyc');
        $avatar = $this->service()->upload($this->blob('a.bin', 'same-content'), File::PUBLIC, null, null, 'avatar');

        $this->assertNotSame($kyc->id, $avatar->id);
        $this->assertSame(2, File::query()->count());
    }

    public function test_identical_bytes_under_a_different_privilege_are_stored_separately(): void {
        $open = $this->service()->upload($this->blob('a.bin', 'same-content'), File::PUBLIC);
        $secret = $this->service()->upload($this->blob('a.bin', 'same-content'), File::PRIVATE);

        $this->assertNotSame($open->id, $secret->id);
        $this->assertSame(2, File::query()->count());
    }

    public function test_an_oversize_upload_is_refused_and_leaves_nothing_behind(): void {
        $this->refuses('file-too-large', fn () => $this->service()->upload($this->blob('big.bin', str_repeat('x', 2048)), File::PUBLIC, 1024));

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_unaccepted_mime_type_is_refused_and_leaves_nothing_behind(): void {
        $this->refuses('invalid-mime-type', fn () => $this->service()->upload($this->blob('doc.pdf', 'x'), File::PUBLIC, null, ['#^image/#']));

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_zero_size_limit_means_no_limit(): void {
        $file = $this->service()->upload($this->blob('big.bin', str_repeat('x', 2048)), File::PUBLIC, 0);

        $this->assertSame(2048, $file->size);
    }

    public function test_the_package_imposes_no_size_or_type_limit_of_its_own(): void {
        $file = $this->service()->upload(UploadedFile::fake()->create('huge.pdf', 51200, 'application/pdf'));

        $this->assertSame(51200 * 1024, $file->size);
    }

    public function test_a_configured_limit_applies_when_the_caller_passes_nothing(): void {
        $this->useCfgFixtures();

        $this->refuses('file-too-large', fn () => $this->service()->upload($this->blob('big.png', str_repeat('x', 2048))));
    }

    public function test_a_configured_mime_whitelist_applies_when_the_caller_passes_nothing(): void {
        $this->useCfgFixtures();

        $this->refuses('invalid-mime-type', fn () => $this->service()->upload($this->blob('doc.pdf', 'x')));
    }

    public function test_the_stored_path_carries_the_month_and_no_leading_slash(): void {
        $file = $this->service()->upload($this->blob('a.bin', 'content'));

        $this->assertMatchesRegularExpression('#^' . date('Ym') . '/[A-Za-z0-9]{32}\.bin$#', $file->path);
        $this->assertSame($file->path, $this->reload($file)->path);
    }

    public function test_a_file_without_an_extension_gets_no_trailing_dot(): void {
        $file = $this->service()->upload($this->blob('README', 'content'));

        $this->assertMatchesRegularExpression('#^' . date('Ym') . '/[A-Za-z0-9]{32}$#', $file->path);
    }

    public function test_a_rollback_removes_the_file_that_was_just_written(): void {
        $stored = null;

        $this->rollback(function () use (&$stored): void {
            $stored = $this->service()->upload($this->blob('a.bin', 'content'))->path;
        });

        $this->assertNotNull($stored);
        Storage::disk('public')->assertMissing("files/{$stored}");
    }

    public function test_a_deduplicated_upload_registers_no_cleanup_for_the_file_it_reused(): void {
        $content = 'already-here';
        $path = date('Ym') . '/' . Str::random(32) . '.bin';

        Storage::disk('public')->put("files/{$path}", $content);

        File::forceCreate([
            'name' => 'seeded.bin',
            'path' => $path,
            'size' => strlen($content),
            'hash' => hash('sha256', $content),
            'privilege' => File::PUBLIC
        ]);

        $this->rollback(function () use ($content): void {
            $this->service()->upload($this->blob('other.bin', $content));
        });

        Storage::disk('public')->assertExists("files/{$path}");
    }

    public function test_a_stored_file_resolves_to_its_bytes_on_the_disk_its_privilege_selects(): void {
        $file = $this->service()->upload($this->blob('secret.bin', 'top-secret'), File::PRIVATE);
        $found = $this->service()->find($file->path);
        $disk = $this->service()->disk($found->privilege);

        $this->assertSame('secret.bin', $found->name);
        $this->assertSame('local', $disk);
        $this->assertSame('top-secret', Storage::disk($disk)->get($this->service()->location($found)));
    }

    public function test_looking_up_an_unknown_path_fails_to_find_the_record(): void {
        $this->expectException(ModelNotFoundException::class);

        $this->service()->find('nowhere/missing.bin');
    }

    public function test_an_update_renames_the_file_and_is_audited(): void {
        $file = $this->service()->upload($this->blob('a.bin', 'content'));

        $updated = $this->service()->update($file->path, 'renamed.bin', 'note');

        $this->assertSame('renamed.bin', $updated->name);
        $this->assertSame('note', $updated->description);
        $this->assertSame('renamed.bin', array_get_value($this->audited('base_file'), 'name'));
    }

    public function test_the_creation_is_audited_as_a_complete_snapshot(): void {
        $file = $this->service()->upload($this->blob('a.bin', 'content'));
        $after = $this->audited('base_file');

        $this->assertSame($file->name, array_get_value($after, 'name'));
        $this->assertSame($file->path, array_get_value($after, 'path'));
        $this->assertSame($file->hash, array_get_value($after, 'hash'));
    }

    public function test_an_audio_upload_records_its_duration(): void {
        $file = $this->reload($this->service()->upload($this->tone()));

        $this->assertSame(2, $file->seconds);
        $this->assertStringStartsWith('audio/', strval($file->mime_type));
    }

    public function test_a_plain_file_leaves_the_media_columns_empty(): void {
        $file = $this->reload($this->service()->upload($this->blob('a.bin', 'content')));

        $this->assertNull($file->width);
        $this->assertNull($file->height);
        $this->assertNull($file->seconds);
    }

    public function test_the_packaged_limits_are_declared_with_usable_types(): void {
        $this->assertIsInt(cfg('file.max-size'));
        $this->assertIsArray(cfg('file.mime-patterns'));
    }

}
