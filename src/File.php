<?php

namespace FtpSync;

/**
 * Used to inject file functionality into the app in a way that makes
 * the main class unit-testable.
 */
class File
{
    public function require(string $filename): array
    {
        return require($filename);
    }

    public function fileExists($filename): bool
    {
        return file_exists($filename);
    }

    public function isDir(string $filename): bool
    {
        return is_dir($filename);
    }

    public function isWriteable(string $filename): bool
    {
        return is_writeable($filename);
    }

    public function filesize(string $filename): int
    {
        return filesize($filename);
    }

    public function glob(string $pattern): array
    {
        return glob($pattern);
    }

    /**
     * Adds a line to the end of a file (useful for log files)
     *
     * Note: the date() call could be mocked, but for such a simple call, it's hardly
     * worth it. Maybe one for later.
     */
    public function appendLine(string $filename, string $line, bool $addTimestamp = true): void
    {
        $timestamp = $addTimestamp ? '[' . date('c') . ']' : '';
        file_put_contents($filename, "{$timestamp}{$line}\n", FILE_APPEND);
    }
}
