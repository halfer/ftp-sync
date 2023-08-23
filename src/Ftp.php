<?php

namespace FtpSync;

/**
 * A dependency wrapper around the FTP functions that makes the main class unit-testable.
 *
 * Most of the public methods in this class roughly follow the FTP functions, though where
 * a handle parameter is required, that is stored in a class property instead.
 */
class Ftp
{
    protected $handle;

    public function __construct()
    {
    }

    public function connect(string $hostname, int $port, int $timeout): bool
    {
        $handle = ftp_connect($hostname, $port, $timeout);
        if ($handle) {
            $this->handle = $handle;
        }

        return (bool) $handle;
    }

    public function login(string $username, string $password): bool
    {
        return ftp_login($this->handle, $username, $password);
    }

    public function pasv(bool $enable): bool
    {
        return ftp_pasv($this->handle, $enable);
    }

    public function chdir(string $directory): bool
    {
        return ftp_chdir($this->handle, $directory);
    }

    public function mlsd(string $directory): array
    {
        return ftp_mlsd($this->handle, $directory);
    }

    public function get(string $localFilename, string $remoteFilename, int $mode = FTP_BINARY): bool
    {
        return ftp_get($this->handle, $localFilename, $remoteFilename, $mode);
    }

    public function close(): void
    {
        ftp_close($this->handle);
    }
}
