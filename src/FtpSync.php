<?php

namespace FtpSync;

class FtpSync
{
    /* @var File $file */
    protected $file;
    /* @var Ftp $ftp */
    protected $ftp;
    /* @var Output $output */
    protected $output;
    /* @var PhpExtensions $phpExtensions */
    protected $phpExtensions;
    /* @var string $projectRoot */
    protected $projectRoot;
    protected $pathNames = [];
    protected $options = [];

    public function __construct(
        File $file, Ftp $ftp, Output $output,
        PhpExtensions $phpExtensions,
        string $projectRoot, array $pathNames, array $options = []
    )
    {
        $this->file = $file;
        $this->ftp = $ftp;
        $this->output = $output;
        $this->phpExtensions = $phpExtensions;
        $this->projectRoot = $projectRoot;
        $this->pathNames = $pathNames;
        $this->options = $options;
    }

    public function run(): void
    {
        // Initial checks, fetch the config
        $this->checkEnvironment();
        $config = $this->getConfig($this->getConfigPath());

        // Ensure we can write to the sync target directory
        $localDirectory = $this->getLocalDirectory($config);
        $this->ensureLocalTargetDirectoryExists($localDirectory);
        $this->ensureLocalTargetDirectoryIsWriteable($localDirectory);

        // Connect to the FTP server
        $this->makeConnection($config);
        $this->setFtpOptions($config);

        // Generate the file indexes on both sides
        $localIndex = $this->getLocalIndex($localDirectory, $config);
        $remoteDirectory = $this->getRemoteDirectory($config);
        $remoteIndex = $this->getRemoteIndex($remoteDirectory, $config);
        $fileList = $this->indexDifferencer($remoteIndex, $localIndex);

        $this->changeRemoteDir($remoteDirectory);

        // Now copy a chunk of files
        $this->copyFiles(
            $fileList,
            $localDirectory,
            $this->getFileCopiesPerRun($config)
        );

        $this->ftpClose();
    }

    /**
     * The differencer relies on the binary mode. But we could use timestamps instead in the
     * unlikely event of needing to cater for Windows servers, where binary mode might not
     * work so well (due to differences in line end encodings).
     */
    protected function copyFiles(
        array $fileList,
        string $localDirectory,
        int $copyLimit): void
    {
        foreach (array_values($fileList) as $ord => $file) {
            if ($ord >= $copyLimit) {
                break;
            }
            $this->copyFile($localDirectory, $file);
        }
    }

    protected function copyFile(string $localDirectory, string $file): void
    {
        if ($this->isDryRunMode()) {
            $this->stdOut("Would copy {$file} (dry run)");
            return;
        }

        $ok = $this->getFtp()->get(
            $localDirectory . '/' . $file,
            $file,
            FTP_BINARY
        );
        if ($ok) {
            $this->stdOut("Copy {$file} OK");
        }
    }

    protected function changeRemoteDir(string $remoteDirectory): void
    {
        $ok = $this->getFtp()->chdir($remoteDirectory);
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

    protected function getLocalIndex(string $directory, array $config): array
    {
        $fileList = $this->
            getFile()->
            glob($directory . '/' . $this->getLocalFileFilter($config));
        $localIndex = [];

        // Loop through files and get leaf-names and file sizes
        foreach ($fileList as $file) {
            $localIndex[basename($file)] = $this->getFile()->filesize($file);
        }

        $this->informationalOut(
            $config,
            sprintf('Found %d items in local directory', count($localIndex))
        );

        return $localIndex;
    }

    protected function getRemoteIndex(string $directory, array $config): array
    {
        $fileList = $this->getFtp()->mlsd($directory);
        $filter = $this->getRemoteFileFilter($config);

        // Loop through files and get leaf-names and file sizes
        $remoteIndex = [];
        foreach ($fileList as $file) {
            // Ignore anything that is not a file
            if ($file['type'] !== 'file') {
                continue;
            }

            // Ignore anything that does not match the log file pattern
            if ($filter) {
                $matchesPattern = preg_match($filter, $file['name']);
                if (!$matchesPattern) {
                    continue;
                }
            }

            // For some reason the FTP func returns the size as a string
            $remoteIndex[$file['name']] = (int) $file['size'];
        }

        $this->informationalOut(
            $config,
            sprintf('Found %d items in remote directory', count($remoteIndex))
        );

        return $remoteIndex;
    }

    protected function makeConnection(array $config): void
    {
        $ok = $this->getFtp()->connect(
            $this->getFtpHostName($config),
            $this->getFtpPort($config),
            $this->getFtpTimeout($config)
        );
        if (!$ok) {
            $this->errorAndExit('Could not connect to FTP server');
        }

        $ok = $this->getFtp()->login($this->getFtpUserName($config), $this->getFtpPassword($config));
        if (!$ok) {
            $this->errorAndExit('Could not authenticate to FTP server');
        }

        $this->informationalOut(
            $config,
            sprintf('Connected to host `%s`', $this->getFtpHostName($config))
        );
    }

    protected function setFtpOptions(array $config): void
    {
        if (!$this->getPassive($config)) {
            return;
        }

        $ok = $this->getFtp()->pasv(true);
        if (!$ok) {
            $this->errorAndExit('Could not switch to passive mode');
        }

        $this->informationalOut(
            $config,
            'Switched to PASV mode on host'
        );
    }

    protected function ftpClose(): void
    {
        // Fail silently if it did not work
        @$this->getFtp()->close();
    }

    protected function ensureLocalTargetDirectoryIsWriteable(string $directory): void
    {
        if (!$this->getFile()->isWriteable($directory))
        {
            $this->errorAndExit('Cannot write to the local sync folder');
        }
    }

    protected function ensureLocalTargetDirectoryExists(string $directory): void
    {
        if (!$this->getFile()->isDir($directory))
        {
            $this->errorAndExit('The local sync folder cannot be found');
        }
    }

    protected function isDryRunMode(): bool
    {
        return isset($this->options['dryrun']) && $this->options['dryrun'];
    }

    protected function isWebMode(): bool
    {
        return isset($this->options['web']) && $this->options['web'];
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

    protected function getFtpPort(array $config): int
    {
        $port = 21;
        if (isset($config['port'])) {
            $port = (int) $config['port'];
        }

        return $port;
    }

    protected function getFtpTimeout(array $config): int
    {
        $timeout = 20;
        if (isset($config['timeout']) && $config['timeout']) {
            $timeout = (int) $config['timeout'];
        }

        return $timeout;
    }

    protected function getPassive(array $config): bool
    {
        $pasv = true; // Default
        if (isset($config['pasv']) && $config['pasv'] === false) {
            $pasv = false;
        }

        return $pasv;
    }

    protected function getLocalFileFilter(array $config): string
    {
        $filter = '*';
        if (isset($config['local_file_filter']) && $config['local_file_filter']) {
            $filter = $config['local_file_filter'];
        }

        return $filter;
    }

    protected function getRemoteFileFilter(array $config): string
    {
        $filter = '';
        if (isset($config['remote_file_filter']) && $config['remote_file_filter']) {
            $filter = $config['remote_file_filter'];
        }

        return $filter;
    }

    protected function getFileCopiesPerRun(array $config): int
    {
        $copies = 10;
        if (isset($config['file_copies_per_run']) && $config['file_copies_per_run']) {
            $copies = $config['file_copies_per_run'];
        }

        return $copies;
    }

    protected function getLocalLogPath(array $config): string
    {
        $path = '';
        if (isset($config['log_path']) && $config['log_path']) {
            $path = $config['log_path'];
        }

        return $path;
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
        if (!$this->getFile()->fileExists($configPath)) {
            $this->errorAndExit('Cannot find config file');
        }

        return $this->getFile()->require($configPath);
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

    protected function checkEnvironment(): void
    {
        if (!$this->getPhpExtensions()->extensionLoaded('ftp')) {
            $this->errorAndExit("Cannot find PHP 'ftp' extension");
        }
    }

    protected function getPhpExtensions(): PhpExtensions
    {
        return $this->phpExtensions;
    }

    /**
     * Gets the (mockable) object wrapper for file ops
     */
    protected function getFile(): File
    {
        return $this->file;
    }

    /**
     * Gets the (mockable) object wrapper for FTP ops
     */
    protected function getFtp(): Ftp
    {
        return $this->ftp;
    }

    /**
     * Reports progress and useful info if the verbose option is enabled
     *
     * (Maybe when we are operating in web mode, we also send this to stdout?)
     */
    protected function informationalOut(array $config, string $message): void
    {
        if ($logPath = $this->getLocalLogPath($config)) {
            $this->getFile()->appendLine($logPath, $message);
        }
    }

    protected function stdOut(string $message): void
    {
        $this->output->println(
            $message .
            ($this->isWebMode() ? '<br>' : '')
        );
    }

    /**
     * @todo The non-zero exit code should only be for console usage
     */
    protected function errorAndExit(string $message): void
    {
        $this->output->println("Fatal error: $message", 1);
    }
}
