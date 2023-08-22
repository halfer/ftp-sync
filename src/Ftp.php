<?php

namespace FtpSync;

/**
 * At the time of writing this is unused, but it would be useful to inject FTP functionality
 * into the app in a way that makes the main script unit-testable.
 *
 * Most of the public methods in this class roughly follow the FTP functions, though where
 * a handle parameter is required, that is stored in a class property instead. Also, the
 * functions here will throw an exception if they were unsuccessful, and thus a boolean
 * return value for success is not required.
 */
class Ftp
{
    protected $handle;
    protected $fileFilter;

    public function __construct()
    {
    }

    public function connect(string $hostname, int $port, int $timeout): void
    {
        // FIXME
    }

    public function login(string $username, string $password): void
    {
        // FIXME
    }

    public function pasv(bool $enable): void
    {
        // FIXME
    }

    public function chDir(string $directory): void
    {
        // FIXME
    }

    public function mlsd(string $directory): array
    {
        return []; // FIXME
    }

    /**
     * This acts as a cheeky extension to mlsd() - files have to match the regexp pattern
     * in order to be included in the list.
     */
    public function setFileFilter(string $fileFilter): void
    {
        $this->fileFilter = $fileFilter;
    }

    public function get(string $localFilename, string $remoteFilename, int $mode = FTP_BINARY): string
    {
        return ''; // FIXME
    }

    public function close(): void
    {
        // FIXME
    }
}
