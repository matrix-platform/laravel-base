<?php //>

namespace Tests\Feature\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\FileService;
use Tests\Factories\UserFactory;
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

    private function corruptImagePath(): string {
        $path = tempnam(sys_get_temp_dir(), 'corrupt') . '.jpg';

        file_put_contents($path, 'not-a-real-image');

        return $path;
    }

    private function driveFile(string $name = 'cover.jpg', ?DriveNode $parent = null): DriveNode {
        $node = new DriveNode();

        $node->parent_id = $parent === null ? DriveNode::ROOT : $parent->id;
        $node->type = DriveNodeType::File;
        $node->name = $name;
        $node->hash = 'hash-' . $name;
        $node->path = date('Ym') . '/' . Str::random(32);
        $node->size = 1234;
        $node->mime_type = 'image/jpeg';
        $node->width = 800;
        $node->height = 600;

        $node->save();

        return $node;
    }

    private function driveFolder(): DriveNode {
        $node = new DriveNode();

        $node->parent_id = DriveNode::ROOT;
        $node->type = DriveNodeType::Folder;
        $node->name = 'folder';

        $node->save();

        return $node;
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function imagePath(int $width, int $height): string {
        $path = tempnam(sys_get_temp_dir(), 'img') . '.png';

        imagepng(imagecreatetruecolor($width, $height), $path);

        return $path;
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

    private function user(?int $groupId = null): User {
        return UserFactory::new()->createOne(['group_id' => $groupId]);
    }

    private function tone(): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'tone') . '.wav';

        copy(__DIR__ . '/../../fixtures/media/tone.wav', $path);

        return new UploadedFile($path, 'tone.wav', null, null, true);
    }

    public function test_bytes_parses_php_ini_shorthand_units(): void {
        $service = $this->service();

        $this->assertSame(8 * 1024 * 1024, $service->bytes('8M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, $service->bytes('2G'));
        $this->assertSame(512 * 1024, $service->bytes('512K'));
        $this->assertSame(100, $service->bytes('100'));
        $this->assertSame(0, $service->bytes('0'));
    }

    public function test_max_upload_size_returns_a_non_negative_byte_count(): void {
        $this->assertGreaterThanOrEqual(0, $this->service()->maxUploadSize());
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

    public function test_a_reupload_after_the_disk_file_vanished_creates_a_new_record(): void {
        $first = $this->service()->upload($this->blob('a.bin', 'same-content'));

        Storage::disk('public')->delete("files/{$first->path}");

        $second = $this->service()->upload($this->blob('a.bin', 'same-content'));

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->path, $second->path);

        Storage::disk('public')->assertExists("files/{$second->path}");
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

        $this->assertMatchesRegularExpression('#^' . date('Ym') . '/[A-Za-z0-9]{32}$#', $file->path);
        $this->assertSame($file->path, $this->reload($file)->path);
    }

    public function test_the_stored_path_never_carries_the_extension_the_client_sent(): void {
        $file = $this->service()->upload($this->blob('shell.php', 'content'));

        $this->assertMatchesRegularExpression('#^' . date('Ym') . '/[A-Za-z0-9]{32}$#', $file->path);
        $this->assertSame('shell.php', $file->name);

        Storage::disk('public')->assertExists("files/{$file->path}");
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
        $path = date('Ym') . '/' . Str::random(32);

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

    public function test_is_raster_image_accepts_photographs_but_rejects_svg_and_non_images(): void {
        $service = $this->service();

        $this->assertTrue($service->isRasterImage('image/jpeg'));
        $this->assertTrue($service->isRasterImage('image/png'));
        $this->assertFalse($service->isRasterImage('image/svg+xml'));
        $this->assertFalse($service->isRasterImage('application/pdf'));
        $this->assertFalse($service->isRasterImage(null));
    }

    public function test_orient_rotates_a_sideways_photograph_upright(): void {
        $path = $this->rotatedImagePath();

        $this->service()->orient($path);

        $size = getimagesize($path);

        if ($size === false) {
            $this->fail('failed to read the oriented image size');
        }

        $this->assertSame(10, $size[0]);
        $this->assertSame(20, $size[1]);
    }

    public function test_orient_is_idempotent_once_the_photograph_is_upright(): void {
        $path = $this->rotatedImagePath();

        $this->service()->orient($path);
        $corrected = getimagesize($path);

        $this->service()->orient($path);

        $this->assertSame($corrected, getimagesize($path));
    }

    public function test_orient_leaves_an_upright_photograph_untouched(): void {
        $path = $this->imagePath(20, 10);
        $before = file_get_contents($path);

        $this->service()->orient($path);

        $this->assertSame($before, file_get_contents($path));
    }

    public function test_orient_ignores_a_format_without_exif_support(): void {
        $path = $this->imagePath(4, 4);
        $before = file_get_contents($path);

        $this->service()->orient($path);

        $this->assertSame($before, file_get_contents($path));
    }

    public function test_thumbnail_scales_down_to_the_configured_width_and_encodes_as_webp(): void {
        config()->set('matrix.thumbnail-sizes', ['icon' => 64]);

        $destination = sys_get_temp_dir() . '/thumb-' . Str::random(8) . '/icon.webp';

        $this->service()->thumbnail($this->imagePath(200, 100), $destination, 'icon');

        $this->assertFileExists($destination);
        $this->assertSame('RIFF', substr((string) file_get_contents($destination), 0, 4));
        $this->assertSame('WEBP', substr((string) file_get_contents($destination), 8, 4));

        $size = getimagesize($destination);

        if ($size === false) {
            $this->fail('failed to read the generated thumbnail size');
        }

        $this->assertSame(64, $size[0]);
        $this->assertSame(32, $size[1]);
    }

    public function test_thumbnail_does_not_upscale_a_smaller_source(): void {
        config()->set('matrix.thumbnail-sizes', ['icon' => 64]);

        $destination = sys_get_temp_dir() . '/thumb-' . Str::random(8) . '/icon.webp';

        $this->service()->thumbnail($this->imagePath(20, 10), $destination, 'icon');

        $size = getimagesize($destination);

        if ($size === false) {
            $this->fail('failed to read the generated thumbnail size');
        }

        $this->assertSame(20, $size[0]);
        $this->assertSame(10, $size[1]);
    }

    public function test_thumbnail_does_nothing_for_an_unconfigured_size(): void {
        $destination = sys_get_temp_dir() . '/thumb-' . Str::random(8) . '/icon.webp';

        $this->service()->thumbnail($this->imagePath(20, 10), $destination, 'not-configured');

        $this->assertFileDoesNotExist($destination);
    }

    public function test_thumbnail_creates_the_destination_directory(): void {
        config()->set('matrix.thumbnail-sizes', ['icon' => 64]);

        $directory = sys_get_temp_dir() . '/thumb-' . Str::random(8);

        $this->service()->thumbnail($this->imagePath(20, 10), "{$directory}/nested/icon.webp", 'icon');

        $this->assertFileExists("{$directory}/nested/icon.webp");
    }

    public function test_thumbnail_does_not_regenerate_an_existing_file(): void {
        config()->set('matrix.thumbnail-sizes', ['icon' => 64]);

        $directory = sys_get_temp_dir() . '/thumb-' . Str::random(8);
        $destination = "{$directory}/icon.webp";

        mkdir($directory, recursive: true);
        file_put_contents($destination, 'already-here');

        $this->service()->thumbnail($this->imagePath(20, 10), $destination, 'icon');

        $this->assertSame('already-here', file_get_contents($destination));
    }

    public function test_thumbnail_reports_a_decode_failure_instead_of_leaking_the_underlying_exception(): void {
        config()->set('matrix.thumbnail-sizes', ['icon' => 64]);

        $destination = sys_get_temp_dir() . '/thumb-' . Str::random(8) . '/icon.webp';

        $this->refuses('image-decode-failed', fn () => $this->service()->thumbnail($this->corruptImagePath(), $destination, 'icon'));
        $this->assertFileDoesNotExist($destination);
    }

    public function test_an_uploaded_photograph_is_oriented_before_its_size_and_hash_are_recorded(): void {
        $path = $this->rotatedImagePath();
        $rawSize = filesize($path);

        $file = $this->reload($this->service()->upload($this->uploadedFrom($path, 'sideways.jpg')));

        $this->assertSame(10, $file->width);
        $this->assertSame(20, $file->height);
        $this->assertNotSame($rawSize, $file->size);

        $stored = Storage::disk('public')->get("files/{$file->path}");

        $this->assertSame(strlen((string) $stored), $file->size);
    }

    public function test_the_packaged_limits_are_declared_with_usable_types(): void {
        $this->assertIsInt(cfg('file.max-size'));
        $this->assertIsString(cfg('file.mime-patterns'));
    }

    public function test_resolving_the_same_drive_node_twice_reuses_the_same_base_file(): void {
        $node = $this->driveFile();
        $actor = $this->user();

        $first = $this->service()->resolveDriveReferences([['id' => $node->id]], $actor);
        $second = $this->service()->resolveDriveReferences([['id' => $node->id]], $actor);

        $this->assertSame($first[0]['path'], $second[0]['path']);
        $this->assertSame(1, File::query()->where('path', $first[0]['path'])->count());
    }

    public function test_resolving_a_drive_node_produces_the_expected_shape(): void {
        $node = $this->driveFile();

        $resolved = $this->service()->resolveDriveReferences([['id' => $node->id]], $this->user())[0];

        $this->assertSame(File::DRIVE_PREFIX . $node->path, $resolved['path']);
        $this->assertSame($node->name, $resolved['name']);
        $this->assertSame($node->mime_type, $resolved['mime_type']);
        $this->assertSame($node->size, $resolved['size']);
        $this->assertSame($node->width, $resolved['width']);
        $this->assertSame($node->height, $resolved['height']);
        $this->assertArrayNotHasKey('type', $resolved);
    }

    public function test_an_unknown_drive_id_is_rejected(): void {
        try {
            $this->service()->resolveDriveReferences([['id' => 999999]], $this->user());

            $this->fail('expected an exception');
        } catch (ServiceException $exception) {
            $this->assertSame('invalid-drive-file', $exception->getError());
        }
    }

    public function test_a_folder_node_is_rejected(): void {
        $folder = $this->driveFolder();

        try {
            $this->service()->resolveDriveReferences([['id' => $folder->id]], $this->user());

            $this->fail('expected an exception');
        } catch (ServiceException $exception) {
            $this->assertSame('invalid-drive-file', $exception->getError());
        }
    }

    public function test_a_node_outside_the_actors_reach_is_refused(): void {
        $owner = $this->user();
        $home = new DriveNode();

        $home->id = $owner->id;
        $home->parent_id = null;
        $home->type = DriveNodeType::Root;
        $home->name = $owner->username;

        $home->save();

        $node = $this->driveFile(parent: $home);
        $stranger = $this->user();

        try {
            $this->service()->resolveDriveReferences([['id' => $node->id]], $stranger);

            $this->fail('expected an exception');
        } catch (ServiceException $exception) {
            $this->assertSame('permission-denied', $exception->getError());
            $this->assertSame(403, $exception->getCode());
        }
    }

    public function test_an_entry_without_an_id_is_passed_through_unchanged(): void {
        $entry = ['name' => 'existing.jpg', 'path' => File::DRIVE_PREFIX . 'already-resolved', 'width' => 100, 'height' => 100];

        $resolved = $this->service()->resolveDriveReferences([$entry], $this->user());

        $this->assertSame([$entry], $resolved);
    }

    public function test_a_mixed_array_only_resolves_the_entry_carrying_an_id(): void {
        $node = $this->driveFile();
        $existing = ['name' => 'existing.jpg', 'path' => File::DRIVE_PREFIX . 'already-resolved', 'width' => 100, 'height' => 100];

        $resolved = $this->service()->resolveDriveReferences([$existing, ['id' => $node->id]], $this->user());

        $this->assertSame($existing, $resolved[0]);
        $this->assertSame(File::DRIVE_PREFIX . $node->path, $resolved[1]['path']);
    }

}
