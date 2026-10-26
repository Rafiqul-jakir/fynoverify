<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests;

use Rafiquljakir\FynoVerify\FynoVerifyServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected string $projectRoot;

    protected function getPackageProviders($app): array
    {
        return [FynoVerifyServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Each test gets an isolated, throwaway project root so file checks and report
        // writes never touch real application files.
        $this->projectRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fynoverify-'.bin2hex(random_bytes(6));
        mkdir($this->projectRoot.'/storage', 0777, true);
        mkdir($this->projectRoot.'/tests/Feature', 0777, true);
        $this->app->setBasePath($this->projectRoot);

        // Keep audits away from any routes the Testbench skeleton registers.
        config()->set('fynoVerify.coverage.include_uri_patterns', ['fvt/*']);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->projectRoot);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: string}
     */
    protected function run_(string $command, array $options = []): array
    {
        $code = Artisan::call($command, $options);

        return [$code, Artisan::output()];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function runJson(string $command, array $options = []): array
    {
        [$code, $output] = $this->run_($command, $options + ['--format' => 'json']);

        return [$code, json_decode($output, true, 512, JSON_THROW_ON_ERROR)];
    }

    protected function touchFile(string $relative): void
    {
        $path = $this->projectRoot.'/'.$relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "<?php\n// evidence fixture\n");
    }

    /**
     * @return list<string>
     */
    protected function snapshot(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = $file->getPathname().'|'.$file->getMTime().'|'.$file->getSize();
        }
        sort($files);

        return $files;
    }

    private function deleteTree(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->deleteTree($path.DIRECTORY_SEPARATOR.$entry);
            }
        }

        @rmdir($path);
    }
}
