<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class ManipulationLogControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $token, array $input): TestResponse {
        return $this->withToken($token)->postJson('admin/manipulation-log/query', $input);
    }

    public function test_it_reports_the_matching_records_history(): void {
        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);

        $group->title__tw = 'B';
        $group->save();

        $response = $this->send($this->token, ['prefix' => 'group', 'id' => $group->id]);

        $response->assertJsonPath('data.rows.0.type', 'Updated');
        $response->assertJsonPath('data.pagination.total', 2);
    }

    public function test_a_missing_prefix_is_a_validation_failure(): void {
        $this->send($this->token, ['id' => 1])->assertJsonPath('fields.prefix', ['required']);
    }

    public function test_a_user_without_the_query_permission_is_denied(): void {
        $group = GroupFactory::new()->createOne();
        $token = UserFactory::new()->createOne(['id' => 1001, 'permissions' => []])->createToken();

        $this->send($token, ['prefix' => 'group', 'id' => $group->id])
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_a_huge_size_request_is_capped_at_one_hundred(): void {
        $group = GroupFactory::new()->createOne();

        $this->send($this->token, ['prefix' => 'group', 'id' => $group->id, 'size' => 100000])
            ->assertJsonPath('data.pagination.size', 100);
    }

}
