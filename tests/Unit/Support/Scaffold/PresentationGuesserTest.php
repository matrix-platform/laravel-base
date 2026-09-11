<?php //>

namespace Tests\Unit\Support\Scaffold;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Scaffold\PresentationGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PresentationGuesserTest extends TestCase {

    public function test_a_column_named_exactly_password_is_guessed_as_password(): void {
        $result = (new PresentationGuesser())->guess('password', ColumnType::Text);

        $this->assertSame(Presentation::Password, $result['presentation']);
        $this->assertFalse($result['sensitive']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sensitiveNameProvider(): array {
        return [
            'contains token' => ['api_token'],
            'contains key' => ['secret_key'],
            'contains secret' => ['client_secret'],
            'contains credential' => ['credential_value'],
            'contains hash' => ['password_hash'],
            'contains pwd' => ['user_pwd']
        ];
    }

    #[DataProvider('sensitiveNameProvider')]
    public function test_a_suspicious_but_not_exact_name_fails_closed_to_hidden(string $name): void {
        $result = (new PresentationGuesser())->guess($name, ColumnType::Text);

        $this->assertSame(Presentation::Hidden, $result['presentation']);
        $this->assertTrue($result['sensitive']);
    }

    public function test_a_boolean_column_is_guessed_as_a_switch(): void {
        $result = (new PresentationGuesser())->guess('is_active', ColumnType::Boolean);

        $this->assertSame(Presentation::Switch, $result['presentation']);
        $this->assertFalse($result['sensitive']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function driveImageNameProvider(): array {
        return [
            'icon' => ['icon'],
            'image' => ['cover_image'],
            'photo' => ['photo'],
            'cover' => ['cover'],
            'avatar' => ['avatar'],
            'thumbnail' => ['thumbnail']
        ];
    }

    #[DataProvider('driveImageNameProvider')]
    public function test_a_jsonb_column_hinting_at_an_image_is_guessed_as_drive_image(string $name): void {
        $result = (new PresentationGuesser())->guess($name, ColumnType::Json);

        $this->assertSame(Presentation::DriveImage, $result['presentation']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function driveFileNameProvider(): array {
        return [
            'file' => ['file'],
            'attachment' => ['attachment'],
            'document' => ['document']
        ];
    }

    #[DataProvider('driveFileNameProvider')]
    public function test_a_jsonb_column_hinting_at_a_file_is_guessed_as_drive_file(string $name): void {
        $result = (new PresentationGuesser())->guess($name, ColumnType::Json);

        $this->assertSame(Presentation::DriveFile, $result['presentation']);
    }

    public function test_a_jsonb_column_without_a_recognizable_hint_is_left_unguessed(): void {
        $result = (new PresentationGuesser())->guess('payload', ColumnType::Json);

        $this->assertNull($result['presentation']);
        $this->assertFalse($result['sensitive']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function textareaNameProvider(): array {
        return [
            'description' => ['description'],
            'content' => ['content'],
            'body' => ['body'],
            'intro' => ['intro'],
            'summary' => ['summary'],
            'detail' => ['detail'],
            'note' => ['note'],
            'remark' => ['remark']
        ];
    }

    #[DataProvider('textareaNameProvider')]
    public function test_a_text_column_hinting_at_free_text_is_guessed_as_textarea(string $name): void {
        $result = (new PresentationGuesser())->guess($name, ColumnType::Text);

        $this->assertSame('textarea', $result['presentation']);
    }

    public function test_a_text_column_ending_in_html_is_guessed_as_html(): void {
        $result = (new PresentationGuesser())->guess('agreement_html', ColumnType::Text);

        $this->assertSame('html', $result['presentation']);
    }

    public function test_a_plain_foreign_key_column_is_left_unguessed(): void {
        $result = (new PresentationGuesser())->guess('category_id', ColumnType::Integer);

        $this->assertNull($result['presentation']);
        $this->assertFalse($result['sensitive']);
    }

    public function test_a_completely_unrecognizable_column_is_left_unguessed(): void {
        $result = (new PresentationGuesser())->guess('weight', ColumnType::Float);

        $this->assertNull($result['presentation']);
        $this->assertFalse($result['sensitive']);
    }

}
