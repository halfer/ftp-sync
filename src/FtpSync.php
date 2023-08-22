<?php

namespace FtpSync;

class FtpSync
{
    protected $projectRoot;
    protected $pathNames = [];

    public function __construct(string $projectRoot, array $pathNames)
    {
        $this->projectRoot = $projectRoot;
        $this->pathNames = $pathNames;
    }

    public function run(): void
    {
        // Fetch the config
        $config = $this->getConfig($this->getConfigPath());

        // Ensure we can write to the sync target directory
        $localDirectory = $this->getLocalDirectory($config);
        $this->ensureLocalTargetDirectoryExists($localDirectory);
        $this->ensureLocalTargetDirectoryIsWriteable($localDirectory);

        // Connect to the FTP server
        $handle = $this->makeConnection($config);
        $this->setFtpOptions($handle);

        // Generate the file indexes on both sides
        $localIndex = $this->getLocalIndex($localDirectory);
        $remoteDirectory = $this->getRemoteDirectory($config);
        $remoteIndex = $this->getRemoteIndex($handle, $remoteDirectory);
        $fileList = $this->indexDifferencer($remoteIndex, $localIndex);

        $this->changeRemoteDir($handle, $remoteDirectory);

        // Now copy a chunk of files
        $this->copyFiles(
            $handle,
            $fileList,
            $localDirectory,
            10
        );

        $this->ftpClose($handle);
    }

    /**
     * The differencer relies on the binary mode. But we could use timestamps instead in the
     * unlikely event of needing to cater for Windows servers, where binary mode might not
     * work so well (due to differences in line end encodings).
     */
    protected function copyFiles(
        $handle,
        array $fileList,
        string $localDirectory,
        int $copyLimit): void
    {
        foreach (array_values($fileList) as $ord => $file) {
            if ($ord >= $copyLimit) {
                break;
            }
            $this->copyFile($handle, $localDirectory, $file);
        }
    }

    protected function copyFile($handle, string $localDirectory, string $file): void
    {
        $ok = ftp_get(
            $handle,
            $localDirectory . '/' . $file,
            $file,
            FTP_BINARY
        );
        // @todo Use a function or a dependency to do console/web output
        if ($ok) {
            echo "Copy {$file} OK\n";
        }
    }

    protected function changeRemoteDir($handle, string $remoteDirectory): void
    {
        $ok = ftp_chdir($handle, $remoteDirectory);
        if (!$ok) {
            $this->errorAndExit('Could not change remote directory');
        }
    }

    /**
     * Decides which files from the remote to copy
     *
     * The format of each array is:
     *
     * [ filename1 => size1, filename2 => size2, ... ]
     */
    protected function indexDifferencer(array $remoteIndex, array $localIndex): array
    {
        $differences = [];
        foreach ($remoteIndex as $name => $remoteSize) {
            $copy = false;

            // See if we this file in the local index
            if (isset($localIndex[$name])) {
                if ($localIndex[$name] !== $remoteSize) {
                    // Copy if the file sizes are different
                    $copy = true;
                }
            } else {
                // Copy if we don't have the file in local at all
                $copy = true;
            }

            if ($copy) {
                $differences[] = $name;
            }
        }

        return $differences;
    }

    /**
     * @todo Inject the wildcard from config
     */
    protected function getLocalIndex(string $directory): array
    {
        $fileList = glob($directory . '/*.log');
        $localIndex = [];

        // Loop through files and get leaf-names and file sizes
        foreach ($fileList as $file) {
            $localIndex[basename($file)] = filesize($file);
        }

        return $localIndex;
    }

    /**
     * @todo Inject the wildcard from config
     */
    protected function getRemoteIndex($handle, string $directory): array
    {
        $fileList = ftp_mlsd($handle, $directory);
        $remoteIndex = [];

        // Loop through files and get leaf-names and file sizes
        foreach ($fileList as $file) {
            // Ignore anything that is not a file
            if ($file['type'] !== 'file') {
                continue;
            }

            // Ignore anything that does not match the log file pattern
            $matchesPattern = preg_match('#\.log$#', $file['name']);
            if (!$matchesPattern) {
                continue;
            }

            // For some reason the FTP func returns the size as a string
            $remoteIndex[$file['name']] = (int) $file['size'];
        }

        return $remoteIndex;
    }

    protected function makeConnection(array $config)
    {
        $handle = ftp_connect($this->getFtpHostName($config), 21, 20);
        if (!$handle) {
            $this->errorAndExit('Could not connect to FTP server');
        }

        $ok = ftp_login($handle, $this->getFtpUserName($config), $this->getFtpPassword($config));
        if (!$ok) {
            $this->errorAndExit('Could not authenticate to FTP server');
        }

        return $handle;
    }

    /**
     * @todo Passive mode should probably be a config option
     */
    protected function setFtpOptions($handle): void
    {
        $ok = ftp_pasv($handle, true);
        if (!$ok) {
            $this->errorAndExit('Could not switch to passive mode');
        }
    }

    protected function ftpClose($handle): void
    {
        // Fail silently if it did not work
        @ftp_close($handle);
    }

    protected function ensureLocalTargetDirectoryIsWriteable(string $directory): void
    {
        if (!is_writeable($directory))
        {
            $this->errorAndExit('Cannot write to the local sync folder');
        }
    }

    protected function ensureLocalTargetDirectoryExists(string $directory): void
    {
        if (!is_dir($directory))
        {
            $this->errorAndExit('The local sync folder cannot be found');
        }
    }

    protected function getFtpHostName(array $config): string
    {
        return $this->getConfigKey($config, 'hostname');
    }

    protected function getFtpUserName(array $config): string
    {
        return $this->getConfigKey($config, 'username');
    }

    protected function getFtpPassword(array $config): string
    {
        return $this->getConfigKey($config, 'password');
    }

    protected function getLocalDirectory(array $config): string
    {
        return $this->getConfigKey($config, 'local_directory');
    }

    protected function getRemoteDirectory(array $config): string
    {
        return $this->getConfigKey($config, 'remote_directory');
    }

    protected function getConfigKey(array $config, string $key)
    {
        if (!isset($config[$key])) {
            $this->errorAndExit("Cannot find config key `$key`");
        }

        return $config[$key];
    }

    /**
     * The config file is a PHP script that returns an associative array
     */
    protected function getConfig(string $configPath): array
    {
        if (!file_exists($configPath)) {
            $this->errorAndExit('Cannot find config file');
        }

        return require($configPath);
    }

    protected function getConfigPath(): string
    {
        return $this->projectRoot . '/' . $this->getConfigName('config');
    }

    protected function getConfigName(string $name): string
    {
        if (!isset($this->pathNames[$name])) {
            $this->errorAndExit("Cannot find file name for path type `$name`");
        }

        return $this->pathNames[$name];
    }

    protected function errorAndExit(string $message): void
    {
        echo "Fatal error: $message\n";
        exit(1);
    }
}