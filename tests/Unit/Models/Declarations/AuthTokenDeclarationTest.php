<?php //>

namespace Tests\Unit\Models\Declarations;

use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Models\Declarations\AuthTokenDeclaration;
use PHPUnit\Framework\TestCase;

class AuthTokenDeclarationTest extends TestCase {

    public function test_the_plaintext_token_is_hidden_from_derived_column_lists(): void {
        $definitions = (new AuthTokenDeclaration())->definitions();

        $this->assertSame(Presentation::Hidden, $definitions['token']->presentation);
    }

    public function test_the_plaintext_token_is_not_used_as_the_record_title(): void {
        $metadata = (new AuthTokenDeclaration())->metadata();

        $this->assertNotSame('token', $metadata->title);
        $this->assertSame('type', $metadata->title);
    }

}
