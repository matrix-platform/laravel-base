<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\BlockDataGuard;
use Tests\FeatureTestCase;

class BlockDataGuardTest extends FeatureTestCase {

    private function variant(bool $translatable = false): Variant {
        return new class($translatable) implements Variant {

            public function __construct(private bool $translatable) {}

            /**
             * @return array<string, Definition>
             */
            public function definitions(): array {
                return ['flag' => Definition::integer(Presentation::Select, options: new BundleOptions('boolean'), translatable: $this->translatable)];
            }

        };
    }

    public function test_an_integer_select_value_inside_its_options_is_accepted(): void {
        BlockDataGuard::inspect($this->variant(), ['flag' => 1]);
        BlockDataGuard::inspect($this->variant(), ['flag' => 0]);

        $this->addToAssertionCount(1);
    }

    public function test_an_integer_select_value_outside_its_options_is_refused(): void {
        try {
            BlockDataGuard::inspect($this->variant(), ['flag' => 7]);
        } catch (ServiceException $exception) {
            $this->assertSame(['fields' => ['data__flag' => ['in']]], $exception->getExtra());

            return;
        }

        $this->fail('the value was expected to be refused');
    }

    public function test_a_translatable_select_value_is_checked_and_reported_per_locale(): void {
        try {
            BlockDataGuard::inspect($this->variant(true), ['flag' => ['tw' => 1, 'en' => 7]]);
        } catch (ServiceException $exception) {
            $this->assertSame(['fields' => ['data__flag__en' => ['in']]], $exception->getExtra());

            return;
        }

        $this->fail('the value was expected to be refused');
    }

}
