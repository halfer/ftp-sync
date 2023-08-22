<?php

/**
 * This script is a "poor person's sync" system to copy files from one server to another. It uses FTP
 * and builds a list of files on both sides, so that only changed files are copied. Normally we
 * would use SSH/Rsync for this, but this is useful where such systems are not available.
 */

$projectRoot = realpath('.');
$ftpSync = new FtpSync(
    $projectRoot,
    ['config' => 'config.php', 'statefile' => 'statefile.php', ]
);
$ftpSync->run();

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

        /**
         * The original plan was to use a "statefile" to record what had been copied, but since
         * we can generate an index on both sides, I am now not sure that I see the point of this.
         * If the indexing locally & remotely works, I think I will get rid of the statefile
         * stuff.
         */

        // See if the statefile is happy
        #$this->createStateFileIfNecessary($this->getStateFilePath());
        #$this->ensureStateFileIsWriteable($this->getStateFilePath());

        // Ensure we can write to the sync target directory
        $localDirectory = $this->getLocalDirectory($config);
        $this->ensureLocalTargetDirectoryIsWriteable($localDirectory);

        // Connect to the FTP server
        $handle = $this->makeConnection($config);
        $this->setFtpOptions($handle);

        // Generate the file indexes on both sides
        $localIndex = $this->getLocalIndex($localDirectory);
        $remoteDirectory = $this->getRemoteDirectory($config);
        $remoteIndex = $this->getRemoteIndex($handle, $remoteDirectory);
        $fileList = $this->indexDifferencer($localIndex, $remoteIndex);

        // Now copy a chunk of files
        $this->copyFiles(
            $handle,
            $fileList,
            $remoteDirectory,
            $localDirectory,
            20
        );
    }

    /**
     * The differencer relies on the binary mode. But we could use timestamps instead in the
     * unlikely event of needing to cater for Windows servers, where binary mode might not
     * work so well (due to differences in line end encodings.
     */
    protected function copyFiles(
        $handle,
        array $fileList,
        string $remoteDirectory,
        string $localDirectory,
        int $copyLimit): void
    {
        // FIXME
    }

    protected function indexDifferencer(array $localIndex, array $remoteIndex): array
    {
        // FIXME this is completely untested
        return array_diff($localIndex, $remoteIndex);
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
            $localIndex[] = [
                'name' => basename($file),
                'size' => filesize($file),
            ];
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

            $remoteIndex[] = [
                'name' => $file['name'],
                'size' => $file['size'],
            ];
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

    protected function setFtpOptions($handle): void
    {
        $ok = ftp_pasv($handle, true);
        if (!$ok) {
            $this->errorAndExit('Could not switch to passive mode');
        }
    }

    protected function ensureLocalTargetDirectoryIsWriteable(string $directory): void
    {
        if (!is_writeable($directory))
        {
            $this->errorAndExit('Cannot write to the local sync folder');
        }
    }

    protected function ensureStateFileIsWriteable(string $stateFilePath): void
    {
        if (!is_writeable($stateFilePath)) {
            $this->errorAndExit('Cannot write to the local statefile');
        }
    }

    protected function createStateFileIfNecessary(string $stateFilePath): void
    {
        if (!file_exists($stateFilePath)) {
            $this->writeStateFile($stateFilePath, []);
        }
    }

    protected function writeStateFile(string $stateFilePath, array $state): void
    {
        file_put_contents($stateFilePath, json_encode($state));
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

    protected function getConfig(string $configPath): array
    {
        if (!file_exists($configPath)) {
            $this->errorAndExit('Cannot find config file');
        }
        $json = file_get_contents($configPath);

        return json_decode($json, true);
    }

    protected function getStateFilePath(): string
    {
        return $this->projectRoot . '/' . $this->getConfigName('statefile');
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
