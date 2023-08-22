<?php

namespace FtpSync;

/**
 * At the time of writing this is unused, but it would be useful to inject file functionality
 * into the app in a way that makes the main script unit-testable.
 */
class File
{
    public function require(string $filename): array
    {
        return []; // FIXME
    }

    public function isDir(string $filename): bool
    {
        return true; // FIXME
    }

    public function isWriteable(string $filename): bool
    {
        return true; // FIXME
    }

    public function filesize(string $filename): int
    {
        return 0; // FIXME
    }

    public function glob(string $pattern): array
    {
        return []; // FIXME
    }
}
