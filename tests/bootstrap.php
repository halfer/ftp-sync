<?php

// Load dependencies (avoiding Composer for now)
$projectRoot = realpath(__DIR__ . '/..');
foreach (glob($projectRoot . '/src/*.php') as $classFile) {
    require_once $classFile;
}
