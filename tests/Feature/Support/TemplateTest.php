<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\Template;
use Tests\FeatureTestCase;

class TemplateTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMessagingFixtures();
    }

    public function test_a_template_renders_every_field_with_its_variables(): void {
        $fields = Template::render('mail', 'welcome', ['name' => 'Alice']);

        $this->assertSame('Welcome Alice', array_get_value($fields, 'subject'));
        $this->assertSame('Dear Alice, welcome aboard.', array_get_value($fields, 'content'));
    }

    public function test_a_variable_the_caller_did_not_supply_is_left_intact(): void {
        $fields = Template::render('sms', 'otp', []);

        $this->assertSame('Your code is {code}', array_get_value($fields, 'content'));
    }

    public function test_a_non_string_field_passes_through_untouched(): void {
        $fields = Template::render('sms', 'otp', ['code' => '123456']);

        $this->assertSame('Your code is 123456', array_get_value($fields, 'content'));
        $this->assertSame(300, array_get_value($fields, 'ttl'));
    }

    public function test_rendering_an_unknown_template_is_refused(): void {
        $this->expectException(ServiceException::class);

        Template::render('sms', 'does-not-exist');
    }

    public function test_resolving_an_unknown_template_returns_null_instead_of_failing(): void {
        $this->assertNull(Template::resolve('sms', 'does-not-exist'));
    }

    public function test_a_locale_can_be_named_explicitly(): void {
        $this->assertNull(Template::resolve('mail', 'welcome', 'tw'));
        $this->assertNotNull(Template::resolve('mail', 'welcome', 'en'));
    }

    public function test_the_package_ships_no_templates_of_its_own(): void {
        $this->assertSame([], glob(__DIR__ . '/../../../resources/i18n/*/template') ?: []);
    }

}
