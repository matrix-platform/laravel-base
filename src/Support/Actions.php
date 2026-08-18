<?php //>

namespace MatrixPlatform\Support;

class Actions {

    public function __construct(private Resources $resources) {}

    /**
     * @return array<string, mixed>
     */
    public function define(string $type): array {
        $configured = $this->resources->getConfigBundle("action-{$type}");
        $action = $configured === null ? [] : $configured;
        $confirm = array_get_value($action, 'confirm');

        $action['type'] = $type;
        $action['title'] = i18n("actions.{$type}");

        if (is_string($confirm)) {
            $action['confirm'] = i18n($confirm);
        }

        return $action;
    }

}
