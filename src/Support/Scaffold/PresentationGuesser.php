<?php //>

namespace MatrixPlatform\Support\Scaffold;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Presentation;

class PresentationGuesser {

    private const SENSITIVE_PATTERN = '/secret|token|key|credential|hash|pwd/i';

    /**
     * @return array{presentation: Presentation|string|null, sensitive: bool}
     */
    public function guess(string $name, ColumnType $type): array {
        if ($name === 'password') {
            return ['presentation' => Presentation::Password, 'sensitive' => false];
        }

        if (preg_match(self::SENSITIVE_PATTERN, $name) === 1) {
            return ['presentation' => Presentation::Hidden, 'sensitive' => true];
        }

        if ($type === ColumnType::Boolean) {
            return ['presentation' => Presentation::Switch, 'sensitive' => false];
        }

        if ($type === ColumnType::Json) {
            return ['presentation' => $this->jsonPresentation($name), 'sensitive' => false];
        }

        if ($type === ColumnType::Text && $this->matches($name, ['description', 'content', 'body', 'intro', 'summary', 'detail', 'note', 'remark'])) {
            return ['presentation' => 'textarea', 'sensitive' => false];
        }

        if ($type === ColumnType::Text && str_ends_with($name, '_html')) {
            return ['presentation' => 'html', 'sensitive' => false];
        }

        return ['presentation' => null, 'sensitive' => false];
    }

    private function jsonPresentation(string $name): ?Presentation {
        if ($this->matches($name, ['icon', 'image', 'photo', 'cover', 'avatar', 'thumbnail'])) {
            return Presentation::DriveImage;
        }

        if ($this->matches($name, ['file', 'attachment', 'document'])) {
            return Presentation::DriveFile;
        }

        return null;
    }

    /**
     * @param list<string> $keywords
     */
    private function matches(string $name, array $keywords): bool {
        foreach ($keywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

}
