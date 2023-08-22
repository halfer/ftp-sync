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

// Load dependencies (avoiding Composer for now)
$projectRoot = realpath('.');
foreach (glob($projectRoot . '/src/*.php') as $classFile) {
    require_once $classFile;
}

$ftpSync = new FtpSync(
    $projectRoot,
    ['config' => 'config.php', ]
);
$ftpSync->run();
