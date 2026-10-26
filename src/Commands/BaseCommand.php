<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Commands;

use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Support\ReportWriteException;
use Rafiquljakir\FynoVerify\Support\ReportWriter;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Shared behavior for the fyno:* commands: stderr diagnostics, strict option validation,
 * raw (unformatted) report emission and an internal-error guard that never reports success.
 */
abstract class BaseCommand extends Command
{
    /**
     * @param  callable(): int  $callback
     */
    protected function guard(callable $callback): int
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $this->failWith(
                sprintf('Unexpected internal error (%s): %s', $e::class, $e->getMessage()),
                FynoVerify::EXIT_INTERNAL,
            );

            if ($this->output !== null && $this->output->isVerbose()) {
                $this->errorLine($e->getTraceAsString());
            }

            return FynoVerify::EXIT_INTERNAL;
        }
    }

    protected function errorLine(string $message): void
    {
        $this->output->getErrorStyle()->writeln(OutputFormatter::escape($message));
    }

    protected function failWith(string $message, int $code = FynoVerify::EXIT_INVALID): int
    {
        $this->errorLine('[fynoVerify] '.$message);

        return $code;
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function formatOption(array $allowed): ?string
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, $allowed, true)) {
            return null;
        }

        return $format;
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    /**
     * Print a machine-readable/Markdown payload to stdout without formatter interpretation,
     * or write it to --output. Returns an exit code on failure, or null on success.
     *
     * @param  'json'|'markdown'  $format
     */
    protected function emitDocument(string $format, string $content, ReportWriter $writer): ?int
    {
        $output = $this->stringOption('output');

        if ($output === null) {
            $this->output->write($content, false, OutputInterface::OUTPUT_RAW);

            return null;
        }

        try {
            $written = $writer->write($output, $format, $content, base_path(), (bool) $this->option('force'));
        } catch (ReportWriteException $e) {
            return $this->failWith($e->getMessage());
        }

        $this->line(sprintf('Report written to %s', OutputFormatter::escape($written)));

        return null;
    }
}
