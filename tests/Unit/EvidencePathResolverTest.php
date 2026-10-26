<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Support\EvidencePathResolver;
use PHPUnit\Framework\TestCase;

final class EvidencePathResolverTest extends TestCase
{
    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        $this->root = Support::tempDir();
        $this->outside = Support::tempDir();
        mkdir($this->root.'/tests', 0777, true);
        file_put_contents($this->root.'/tests/FooTest.php', '<?php');
        file_put_contents($this->outside.'/secret.php', '<?php');
    }

    protected function tearDown(): void
    {
        Support::deleteTree($this->root);
        Support::deleteTree($this->outside);
    }

    public function test_existing_file_is_accepted(): void
    {
        $this->assertNull((new EvidencePathResolver)->check('tests/FooTest.php', $this->root));
        $this->assertNull((new EvidencePathResolver)->check('./tests//FooTest.php', $this->root));
        $this->assertNull((new EvidencePathResolver)->check('tests\\FooTest.php', $this->root));
    }

    public function test_missing_file_is_reported(): void
    {
        $this->assertStringContainsString('does not exist', (string) (new EvidencePathResolver)->check('tests/Nope.php', $this->root));
    }

    public function test_directories_are_not_regular_files(): void
    {
        $this->assertStringContainsString('not a regular file', (string) (new EvidencePathResolver)->check('tests', $this->root));
    }

    public function test_traversal_is_rejected_even_when_the_target_exists(): void
    {
        $relative = '../'.basename($this->outside).'/secret.php';

        $this->assertStringContainsString('..', (string) (new EvidencePathResolver)->check($relative, $this->root));
    }

    public function test_absolute_paths_are_rejected(): void
    {
        $resolver = new EvidencePathResolver;

        $this->assertStringContainsString('project-relative', (string) $resolver->check($this->outside.'/secret.php', $this->root));
        $this->assertStringContainsString('project-relative', (string) $resolver->validateSyntax('C:\\temp\\x.php'));
    }

    public function test_empty_and_nul_paths_are_rejected(): void
    {
        $resolver = new EvidencePathResolver;

        $this->assertNotNull($resolver->validateSyntax('   '));
        $this->assertNotNull($resolver->validateSyntax("tests/x\0.php"));
        $this->assertNotNull($resolver->validateSyntax('./'));
    }

    public function test_symlink_escaping_the_root_is_rejected(): void
    {
        if (! @symlink($this->outside.'/secret.php', $this->root.'/tests/link.php')) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        $this->assertStringContainsString('outside', (string) (new EvidencePathResolver)->check('tests/link.php', $this->root));
    }

    public function test_symlink_staying_inside_the_root_is_accepted(): void
    {
        if (! @symlink($this->root.'/tests/FooTest.php', $this->root.'/tests/inside.php')) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        $this->assertNull((new EvidencePathResolver)->check('tests/inside.php', $this->root));
    }
}
