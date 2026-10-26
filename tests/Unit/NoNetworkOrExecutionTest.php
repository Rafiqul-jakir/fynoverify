<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A cheap static guard for the MVP promise: no network, no process execution, no eval.
 * It scans the package source; it is not a substitute for review.
 */
final class NoNetworkOrExecutionTest extends TestCase
{
    public function test_source_contains_no_network_or_execution_primitives(): void
    {
        $forbidden = '/\b(curl_\w+|fsockopen|stream_socket_client|proc_open|popen|shell_exec|passthru|system|exec|eval|mail)\s*\(|GuzzleHttp|Illuminate\\\\Support\\\\Facades\\\\Http|Http::|file_get_contents\(\s*[\'"]https?:|\bphp:\/\/(input|stdin)/';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../src', \FilesystemIterator::SKIP_DOTS));
        $checked = 0;

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $checked++;
            $this->assertSame(0, preg_match($forbidden, (string) file_get_contents($file->getPathname())), $file->getFilename().' uses a forbidden primitive.');
        }

        $this->assertGreaterThan(10, $checked);
    }
}
