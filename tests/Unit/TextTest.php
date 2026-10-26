<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Support\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function test_markdown_escapes_table_breaking_and_html_characters(): void
    {
        $escaped = Text::markdown("a|b\nc <script>alert(1)</script> [x](y) `z` *e* _u_ &");

        $this->assertStringNotContainsString("\n", $escaped);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('a\\|b', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringContainsString('&amp;', $escaped);
        $this->assertStringContainsString('\\[x\\]', $escaped);
    }

    public function test_plain_strips_control_characters_including_ansi_escapes(): void
    {
        $plain = Text::plain("\e[31mred\e[0m\t text\r\n");

        $this->assertStringNotContainsString("\e", $plain);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $plain);
    }

    public function test_terminal_escapes_formatter_tags(): void
    {
        $this->assertStringContainsString('\\<error>', Text::terminal('<error>boom</error>'));
    }
}
