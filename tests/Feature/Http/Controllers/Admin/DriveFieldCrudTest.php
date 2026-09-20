<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\DriveGadget;
use Tests\Stubs\DriveGadgetController;
use Tests\Stubs\StubDeclaration;

class DriveFieldCrudTest extends FeatureTestCase {

    private string $token;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(fn () => Route::prefix('drive-gadget')->group(fn () => ActionRoutes::scan(DriveGadgetController::class)));
    }

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(DriveGadget::class, new StubDeclaration(new Metadata('drive-gadget'), [
            'title' => Definition::text(),
            'attachments' => Definition::json(Presentation::DriveImage)
        ]));

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function admin(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    private function driveFile(string $name = 'cover.jpg'): DriveNode {
        $node = new DriveNode();

        $node->parent_id = DriveNode::ROOT;
        $node->type = DriveNodeType::File;
        $node->name = $name;
        $node->hash = 'hash-' . $name;
        $node->path = date('Ym') . '/' . str()->random(32);
        $node->size = 100;
        $node->mime_type = 'image/jpeg';
        $node->width = 400;
        $node->height = 300;

        $node->save();

        return $node;
    }

    public function test_inserting_a_freshly_picked_drive_node_resolves_it_into_a_base_file_link(): void {
        $node = $this->driveFile();

        $this->admin('admin/drive-gadget/insert', [
            'title' => 'Alpha',
            'attachments' => [['id' => $node->id]]
        ])->assertJsonPath('success', true);

        $gadget = DriveGadget::query()->latest('id')->firstOrFail();
        $attachments = $gadget->attachments;

        if ($attachments === null || !array_key_exists(0, $attachments)) {
            $this->fail('expected at least one attachment');
        }

        $this->assertSame(File::DRIVE_PREFIX . $node->path, $attachments[0]['path']);
        $this->assertSame($node->name, $attachments[0]['name']);
    }

    public function test_a_translatable_drive_field_resolves_every_locale_on_insert(): void {
        app(MetadataRegistry::class)->register(DriveGadget::class, new StubDeclaration(new Metadata('drive-gadget'), [
            'title' => Definition::text(),
            'gallery' => Definition::json(Presentation::DriveImage, translatable: true)
        ]));

        $first = $this->driveFile('first.jpg');
        $second = $this->driveFile('second.jpg');

        $this->admin('admin/drive-gadget/insert', [
            'title' => 'Alpha',
            'gallery__tw' => [['id' => $first->id]],
            'gallery__en' => [['id' => $second->id]]
        ])->assertJsonPath('success', true);

        $gadget = DriveGadget::query()->latest('id')->firstOrFail();
        $tw = $gadget->gallery__tw;
        $en = $gadget->gallery__en;

        if ($tw === null || !array_key_exists(0, $tw) || $en === null || !array_key_exists(0, $en)) {
            $this->fail('expected one resolved image per locale');
        }

        $this->assertSame(File::DRIVE_PREFIX . $first->path, $tw[0]['path']);
        $this->assertSame(File::DRIVE_PREFIX . $second->path, $en[0]['path']);
        $this->assertSame($first->name, $tw[0]['name']);
    }

    public function test_a_translatable_drive_field_resolves_a_newly_picked_node_on_update(): void {
        app(MetadataRegistry::class)->register(DriveGadget::class, new StubDeclaration(new Metadata('drive-gadget'), [
            'title' => Definition::text(),
            'gallery' => Definition::json(Presentation::DriveImage, translatable: true)
        ]));

        $first = $this->driveFile('first.jpg');
        $second = $this->driveFile('second.jpg');

        $insert = $this->admin('admin/drive-gadget/insert', [
            'title' => 'Alpha',
            'gallery__tw' => [['id' => $first->id]],
            'gallery__en' => []
        ]);

        $id = (int) $insert->json('data.id');
        $existing = DriveGadget::query()->findOrFail($id)->gallery__tw;

        $this->admin("admin/drive-gadget/{$id}/update", [
            'title' => 'Alpha',
            'gallery__tw' => $existing,
            'gallery__en' => [['id' => $second->id]]
        ])->assertJsonPath('success', true);

        $gadget = DriveGadget::query()->findOrFail($id);
        $en = $gadget->gallery__en;

        if ($en === null || !array_key_exists(0, $en)) {
            $this->fail('expected the second locale to be resolved on update');
        }

        $this->assertSame(File::DRIVE_PREFIX . $second->path, $en[0]['path']);
        $this->assertSame($existing, $gadget->gallery__tw);
    }

    public function test_updating_without_touching_an_existing_drive_field_does_not_error(): void {
        $node = $this->driveFile();

        $insert = $this->admin('admin/drive-gadget/insert', [
            'title' => 'Alpha',
            'attachments' => [['id' => $node->id]]
        ]);

        $id = (int) $insert->json('data.id');
        $gadget = DriveGadget::query()->findOrFail($id);
        $existing = $gadget->attachments;

        $this->admin("admin/drive-gadget/{$id}/update", [
            'title' => 'Beta',
            'attachments' => $existing
        ])->assertJsonPath('success', true);

        $reloaded = DriveGadget::query()->findOrFail($id);

        $this->assertSame('Beta', $reloaded->title);
        $this->assertSame($existing, $reloaded->attachments);
    }

    public function test_updating_with_a_newly_picked_drive_node_replaces_the_link(): void {
        $first = $this->driveFile('first.jpg');
        $second = $this->driveFile('second.jpg');

        $insert = $this->admin('admin/drive-gadget/insert', [
            'title' => 'Alpha',
            'attachments' => [['id' => $first->id]]
        ]);

        $id = (int) $insert->json('data.id');

        $this->admin("admin/drive-gadget/{$id}/update", [
            'title' => 'Alpha',
            'attachments' => [['id' => $second->id]]
        ])->assertJsonPath('success', true);

        $gadget = DriveGadget::query()->findOrFail($id);
        $attachments = $gadget->attachments;

        if ($attachments === null || !array_key_exists(0, $attachments)) {
            $this->fail('expected at least one attachment');
        }

        $this->assertSame(File::DRIVE_PREFIX . $second->path, $attachments[0]['path']);
    }

    public function test_an_unknown_drive_id_is_refused_with_a_validation_style_error(): void {
        $this->admin('admin/drive-gadget/insert', [
            'title' => 'Alpha',
            'attachments' => [['id' => 999999]]
        ])->assertJson(['success' => false, 'error' => 'invalid-drive-file']);
    }

}
