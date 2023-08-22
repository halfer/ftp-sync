<?php

/**
 * This script is a "poor person's sync" system to copy files from one server to another. It uses FTP
 * and builds a list of files on both sides, so that only changed files are copied. Normally we
 * would use SSH/Rsync for this, but this is useful where such systems are not available.
 *
 * There is currently no local delete where a remote file has been removed, but I don't think
 * we will need that.
 */

use FtpSync\FtpSync;
use FtpSync\File;
use FtpSync\Ftp;

// Load dependencies (avoiding Composer for now)
$projectRoot = realpath('.');
foreach (glob($projectRoot . '/src/*.php') as $classFile) {
    require_once $classFile;
}

// If we are in web mode, permit a "dry run" parameter to be supplied
$options = [];
if (isset($_GET)) {
    $options['web'] = true;
    if (isset($_GET['dryrun'])) {
        $options['dryrun'] = true;
    }
}

$ftpSync = new FtpSync(
    new File(),
    new Ftp(),
    $projectRoot,
    ['config' => 'config.php', ],
    $options
);
$ftpSync->run();
