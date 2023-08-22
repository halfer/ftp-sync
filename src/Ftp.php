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
