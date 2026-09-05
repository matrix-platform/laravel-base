<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Translation\TranslationService;

class TranslationController extends BaseController {

    public function __construct(private TranslationService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action('')]
    public function translate(Request $request): array {
        $request->validate([
            'content' => ['required', 'string'],
            'source' => ['required', 'string', Rule::in(locales())],
            'target' => ['required', 'string', Rule::in(locales()), 'different:source']
        ]);

        $translated = $this->service->translate(
            $request->string('content')->value(),
            $request->string('source')->value(),
            $request->string('target')->value()
        );

        return $translated === null ? ['available' => false] : ['available' => true, 'content' => $translated];
    }

}
