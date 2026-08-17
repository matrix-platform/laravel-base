<?php //>

namespace Tests\Feature\Messaging;

use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Support\Resources;
use ReflectionClass;
use Tests\FeatureTestCase;

class MessageStatusTest extends FeatureTestCase {

    /**
     * @return list<int>
     */
    private function values(): array {
        return array_map(fn (MessageStatus $status): int => $status->value, MessageStatus::cases());
    }

    public function test_the_stored_values_are_the_ones_existing_data_already_carries(): void {
        $this->assertSame(1, MessageStatus::Scheduled->value);
        $this->assertSame(2, MessageStatus::Success->value);
        $this->assertSame(3, MessageStatus::Failed->value);
        $this->assertSame(4, MessageStatus::Sending->value);
    }

    public function test_every_status_has_a_label_in_every_channel_bundle_and_locale(): void {
        $values = $this->values();

        sort($values);

        foreach (['mail-log-status', 'sms-log-status'] as $bundle) {
            foreach (['en', 'tw'] as $locale) {
                $labels = app(Resources::class)->getI18nBundle("options/{$bundle}", $locale);

                $this->assertNotNull($labels, "{$bundle}/{$locale}");

                $keys = array_map(intval(...), array_keys($labels));

                sort($keys);

                $this->assertSame($values, $keys, "{$bundle}/{$locale}");
            }
        }
    }

    public function test_the_abstract_base_is_not_offered_to_the_declaration_scanner(): void {
        $reflection = new ReflectionClass(MessageLog::class);

        $this->assertTrue($reflection->isAbstract());
        $this->assertSame([], $reflection->getAttributes(Declared::class));
    }

}
