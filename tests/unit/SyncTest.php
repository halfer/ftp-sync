<?php

namespace FtpSync\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;

class SyncTest extends TestCase
{
    use Mocks;

    public function setUp(): void
    {
        $this->createStandardMocks();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testSimpleFullRun()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile();
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing($this->defaultLocalListing());
        $this->expectRemoteFileListing($this->defaultRemoteFileListing());
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet($this->defaultFtpGetOperations());
        $this->expectFtpCopyOutput(['log03.log']);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    public function testIgnoreRemoteFilesThatDontMatchPattern()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile();
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing($this->defaultLocalListing());
        $remoteListing = $this->defaultRemoteFileListing();
        $remoteListing[2]['name'] = 'log03.txt'; // Not *.log
        $this->expectRemoteFileListing($remoteListing);
        $this->expectFtpChangeDirectory();
        // The only difference doesn't match the remote pattern
        $this->expectFtpGet([]);
        $this->expectFtpCopyOutput([]);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    public function testNonStandardFtpPortNumber()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile(['port' => 9999, ]);
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection(9999);
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing($this->defaultLocalListing());
        $this->expectRemoteFileListing($this->defaultRemoteFileListing());
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet($this->defaultFtpGetOperations());
        $this->expectFtpCopyOutput(['log03.log']);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    public function testDontSwitchToPassiveMode() {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile(['pasv' => false, ]);
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions(false);
        $this->expectLocalFileListing($this->defaultLocalListing());
        $this->expectRemoteFileListing($this->defaultRemoteFileListing());
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet($this->defaultFtpGetOperations());
        $this->expectFtpCopyOutput(['log03.log']);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    public function testNonStandardFtpTimeout()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile(['timeout' => 60, ]);
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection(21, 60);
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing($this->defaultLocalListing());
        $this->expectRemoteFileListing($this->defaultRemoteFileListing());
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet($this->defaultFtpGetOperations());
        $this->expectFtpCopyOutput(['log03.log']);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    /**
     * Since the default filter is *.log, we try removing the remote filter here
     */
    public function testLocalIndexFilter()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile([
            // Need the two filters to agree for this test. "*" for local is the
            // same as empty for remote - they both mean "fetch all".
            'local_file_filter' => '*',
            'remote_file_filter' => '',
        ]);
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $localListing = $this->defaultLocalListing();
        $localListing['/local_dir/log03.txt'] = 200; // Size is different
        $this->expectLocalFileListing($localListing, '*'); // Non-standard glob expectation
        $remoteListing = $this->defaultRemoteFileListing();
        $remoteListing[2]['name'] = 'log03.txt'; // Not *.log
        $this->expectRemoteFileListing($remoteListing);
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet([
            ['path' => '/local_dir/log03.txt', 'file' => 'log03.txt', ]
        ]);
        $this->expectFtpCopyOutput(['log03.txt']);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    /**
     * Since the default filter is *.log, we try removing the remote filter here
     */
    public function testRemoteIndexFilter()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile(['remote_file_filter' => '', ]);
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing($this->defaultLocalListing());
        $remoteListing = $this->defaultRemoteFileListing();
        $remoteListing[2]['name'] = 'log03.txt'; // Not *.log
        $this->expectRemoteFileListing($remoteListing);
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet([
            ['path' => '/local_dir/log03.txt', 'file' => 'log03.txt', ]
        ]);
        $this->expectFtpCopyOutput(['log03.txt']);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    public function testDefaultFileCopiesPerRun()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile();
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing([]); // Empty
        $this->expectRemoteFileListing($this->largeRemoteFileListing()); // A biggie!
        $this->expectFtpChangeDirectory();
        $this->expectFtpGetUsingCountsOnly(10); // Default counts per op
        $this->expectFtpCopyOutput([
            'log01.log', 'log02.log', 'log03.log', 'log04.log', 'log05.log',
            'log06.log', 'log07.log', 'log08.log', 'log09.log', 'log10.log',
        ]);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }

    public function testCustomFileCopiesPerRun()
    {
        // Expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile(['file_copies_per_run' => 8, ]); // Custom value
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing([]); // Empty
        $this->expectRemoteFileListing($this->largeRemoteFileListing()); // A biggie!
        $this->expectFtpChangeDirectory();
        $this->expectFtpGetUsingCountsOnly(8); // Custom value
        $this->expectFtpCopyOutput([
            'log01.log', 'log02.log', 'log03.log', 'log04.log', 'log05.log',
            'log06.log', 'log07.log', 'log08.log',
        ]);
        $this->expectFtpClose();

        // Execute
        $this->createSUT()->run();

        // Reassure PHPUnit that no assertions is OK
        $this->assertTrue(true);
    }
}
