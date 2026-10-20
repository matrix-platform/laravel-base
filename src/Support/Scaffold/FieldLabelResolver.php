<?php //>

namespace MatrixPlatform\Support\Scaffold;

class FieldLabelResolver {

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

            $notes[] = "Translate the label of '{$name}' into locale '{$locale}' (source: \"{$comment}\"); used a TODO placeholder instead.";
        }

        return ['labels' => $labels, 'notes' => $notes];
    }

}
