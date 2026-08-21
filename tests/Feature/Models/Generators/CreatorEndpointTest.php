<?php //>

namespace Tests\Feature\Models\Generators;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use MatrixPlatform\Models\Generators\CreatorEndpoint;
use Tests\FeatureTestCase;
use Tests\Stubs\Widget;

class CreatorEndpointTest extends FeatureTestCase {

    private function generated(Request $request): mixed {
        RequestFacade::swap($request);

        return (new CreatorEndpoint())->generate(null, new Widget());
    }

    public function test_an_http_request_yields_its_path(): void {
        $this->assertSame('admin/user', $this->generated(Request::create('admin/user', 'POST')));
    }

    public function test_a_request_without_a_remote_address_yields_no_endpoint(): void {
        $request = Request::create('admin/user', 'POST');

        $request->server->remove('REMOTE_ADDR');

        $this->assertNull($this->generated($request));
    }

}
