<?php //>

namespace MatrixPlatform\Support\Scaffold;

use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Translation\TranslationService;

class FieldLabelResolver {

    public function __construct(private TranslationService $translator) {}

    /**
     * @return array{labels: array<string, string>, notes: list<string>}
     */
    public function resolve(ScaffoldPlan $plan, string $locale): array {
        $defaultLocale = app()->getLocale();
        $labels = [];
        $notes = [];

        foreach ($plan->customFields as $name) {
            $comment = $plan->columns[$name]->comment;

            if ($comment === null) {
                continue;
            }

            if ($locale === $defaultLocale) {
                $labels[$name] = $comment;

                continue;
            }

            $translated = $this->translate($comment, $defaultLocale, $locale);

            if ($translated === null) {
                $notes[] = "Could not auto-translate the comment for '{$name}' into locale '{$locale}'; used a TODO placeholder instead.";

                continue;
            }

            $labels[$name] = $translated;
        }

        return ['labels' => $labels, 'notes' => $notes];
    }

    private function translate(string $comment, string $sourceLocale, string $targetLocale): ?string {
        if (!in_array($sourceLocale, locales(), true) || !in_array($targetLocale, locales(), true)) {
            return null;
        }

        try {
            return $this->translator->translate($comment, $sourceLocale, $targetLocale);
        } catch (ServiceException) {
            return null;
        }
    }

}
