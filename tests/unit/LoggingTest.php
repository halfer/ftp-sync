<?php

namespace FtpSync\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;

class LoggingTest extends TestCase
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

    public function testFullRunWithLogging()
    {
        // Logging expectations
        $this->expectInformationLog('Connected to host `ftp.example.com`');
        $this->expectInformationLog('Switched to PASV mode on host');
        $this->expectInformationLog('Found 2 items in local directory');
        $this->expectInformationLog('Found 3 items in remote directory');
        $this->expectInformationLog('Index differences: 1 missing in local, 0 of different size');

        // Standard expectations
        $this->expectPhpExtensions();
        $this->expectConfigFile(['log_path' => '/local_log_dir/log.log']);
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

    protected function expectInformationLog(string $message)
    {
        $this->
            getMockFile()->
            shouldReceive('appendLine')->
            once()->
            with('/local_log_dir/log.log', $message);
    }
}
