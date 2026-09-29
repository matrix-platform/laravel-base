<?php //>

namespace MatrixPlatform\Support;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Presentation;

class BlockDataGuard {

    const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private static function error(Definition $definition, mixed $value): ?string {
        if ($definition->presentation === Presentation::Select && $definition->options instanceof BundleOptions) {
            $ids = array_map(fn (Option $option): string => (string) $option->id, $definition->options->options());

            return blank($value) || (is_string($value) && in_array($value, $ids, true)) ? null : 'in';
        }

        if ($definition->presentation === Presentation::DriveImage) {
            foreach (is_array($value) ? $value : [] as $file) {
                if (is_array($file) && array_key_exists('mime_type', $file) && !self::image($file)) {
                    return 'image-invalid';
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $file
     */
    private static function image(array $file): bool {
        return in_array(array_get_value($file, 'mime_type'), self::IMAGE_TYPES, true);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function inspect(?Variant $variant, ?array $data): void {
        foreach ($variant === null ? [] : $variant->definitions() as $field => $definition) {
            $value = array_get_value($data, $field);
            $values = [];

            if ($definition->translatable) {
                foreach (locales() as $locale) {
                    $values["data__{$field}__{$locale}"] = is_array($value) ? array_get_value($value, $locale) : null;
                }
            } else {
                $values["data__{$field}"] = $value;
            }

            foreach ($values as $name => $item) {
                $error = self::error($definition, $item);

                if ($error !== null) {
                    invalid($name, $error);
                }
            }
        }
    }

}
