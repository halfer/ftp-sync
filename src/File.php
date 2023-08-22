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
}
