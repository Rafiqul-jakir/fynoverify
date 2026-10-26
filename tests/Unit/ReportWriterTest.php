<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Support\ReportWriteException;
use Rafiquljakir\FynoVerify\Support\ReportWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportWriterTest extends TestCase
{
    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        $this->root = Support::tempDir();
        $this->outside = Support::tempDir();
    }

    protected function tearDown(): void
    {
        Support::deleteTree($this->root);
        Support::deleteTree($this->outside);
    }

    public function test_it_creates_parent_directories_and_writes(): void
    {
        $written = (new ReportWriter)->write('storage/app/fynoverify/routes.json', 'json', "{}\n", $this->root, false);

        $this->assertSame('storage/app/fynoverify/routes.json', $written);
        $this->assertSame("{}\n", file_get_contents($this->root.'/storage/app/fynoverify/routes.json'));
    }

    public function test_it_refuses_to_overwrite_without_force(): void
    {
        $writer = new ReportWriter;
        $writer->write('out/r.json', 'json', 'one', $this->root, false);

        try {
            $writer->write('out/r.json', 'json', 'two', $this->root, false);
            $this->fail('Expected an exception.');
        } catch (ReportWriteException $e) {
            $this->assertStringContainsString('--force', $e->getMessage());
        }

        $this->assertSame('one', file_get_contents($this->root.'/out/r.json'));

        $writer->write('out/r.json', 'json', 'two', $this->root, true);
        $this->assertSame('two', file_get_contents($this->root.'/out/r.json'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsafe(): array
    {
        return [
            'traversal' => ['../escape.json', 'json'],
            'nested traversal' => ['out/../../escape.json', 'json'],
            'absolute unix' => ['/tmp/escape.json', 'json'],
            'absolute windows' => ['C:\\escape.json', 'json'],
            'wrong extension for json' => ['out/report.md', 'json'],
            'wrong extension for markdown' => ['out/report.json', 'markdown'],
            'php file' => ['out/report.php', 'json'],
            'source directory' => ['app/report.json', 'json'],
            'config directory' => ['config/report.json', 'json'],
            'vendor directory' => ['vendor/report.json', 'json'],
            'composer.json' => ['composer.json', 'json'],
            'readme' => ['README.md', 'markdown'],
            'empty' => ['  ', 'json'],
        ];
    }

    #[DataProvider('unsafe')]
    public function test_unsafe_destinations_are_rejected(string $path, string $format): void
    {
        $this->expectException(ReportWriteException::class);

        (new ReportWriter)->write($path, $format, 'x', $this->root, true);
    }

    public function test_symlinked_parent_escaping_the_root_is_rejected(): void
    {
        if (! @symlink($this->outside, $this->root.'/linked')) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        try {
            (new ReportWriter)->write('linked/report.json', 'json', 'x', $this->root, true);
            $this->fail('Expected an exception.');
        } catch (ReportWriteException $e) {
            $this->assertStringContainsString('outside', $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->outside.'/report.json');
    }

    public function test_writing_through_a_symlinked_file_is_rejected(): void
    {
        file_put_contents($this->outside.'/target.json', 'original');

        if (! @symlink($this->outside.'/target.json', $this->root.'/report.json')) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        $this->expectException(ReportWriteException::class);

        try {
            (new ReportWriter)->write('report.json', 'json', 'overwritten', $this->root, true);
        } finally {
            $this->assertSame('original', file_get_contents($this->outside.'/target.json'));
        }
    }

    public function test_a_file_in_the_way_of_a_directory_is_reported(): void
    {
        file_put_contents($this->root.'/blocker', 'x');

        $this->expectException(ReportWriteException::class);

        (new ReportWriter)->write('blocker/report.json', 'json', 'x', $this->root, true);
    }
}
