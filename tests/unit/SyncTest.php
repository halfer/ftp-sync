<?php

use FtpSync\File;
use FtpSync\Ftp;
use FtpSync\FtpSync;
use FtpSync\Output;
use FtpSync\PhpExtensions;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SyncTest extends TestCase
{
    /* @var File $file */
    protected $file;
    /* @var Ftp $ftp */
    protected $ftp;
    /* @var Output $output */
    protected $output;
    /* @var PhpExtensions $phpExtensions */
    protected $phpExtensions;

    public function setUp(): void
    {
        $this->file = Mockery::mock(File::class);
        $this->ftp = Mockery::mock(Ftp::class);
        $this->output = Mockery::mock(Output::class);
        $this->phpExtensions = Mockery::mock(PhpExtensions::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    // *
    // To aid brevity we have a few predefined values that are used often
    // *

    protected function defaultLocalListing(): array
    {
        return [
            '/local_dir/log01.log' => 100,
            '/local_dir/log02.log' => 110,
        ];
    }

    protected function defaultRemoteFileListing(): array
    {
        return [
            // Sizes are string ints when they come out of mlsd()
            ['name' => 'log01.log', 'type' => 'file', 'size' => '100', ],
            ['name' => 'log02.log', 'type' => 'file', 'size' => '110', ],
            ['name' => 'log03.log', 'type' => 'file', 'size' => '120', ],
        ];
    }

    protected function largeRemoteFileListing(): array
    {
        $listing = [];
        for ($i = 0; $i < 20; $i++) {
            $listing[] = [
                'name' => sprintf("log%02d.log", $i + 1),
                'type' => 'file',
                'size' => (string) (100 + $i * 10),
            ];
        }

        return $listing;
    }

    protected function defaultFtpGetOperations(): array
    {
        return [
            ['path' => '/local_dir/log03.log', 'file' => 'log03.log', ]
        ];
    }

    // *
    // End of predefined values
    // *

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

    protected function createSUT()
    {
        return new FtpSync(
            $this->getMockFile(),
            $this->getMockFtp(),
            $this->getMockOutput(),
            $this->getMockPhpExtensions(),
            '/project',
            ['config' => 'config.php']
        );
    }

    protected function expectPhpExtensions(): void
    {
        $this->
            getMockPhpExtensions()->
            shouldReceive('extensionLoaded')->
            once()->
            with('ftp')->
            andReturn(true);
    }

    protected function expectConfigFile(array $extraConfig = []): void
    {
        $config = array_merge(
            [
                'remote_directory' => '/remote_dir',
                'local_directory' => '/local_dir',
                'hostname' => 'ftp.example.com',
                'username' => 'example',
                'password' => 'mypassword',
                "local_file_filter" => "*.log",
                "remote_file_filter" => "#\.log$#",
            ],
            $extraConfig
        );
        $this->
            // First mock
            getMockFile()->
            shouldReceive('fileExists')->
            once()->
            with('/project/config.php')->
            andReturn(true)->
            // Second mock
            shouldReceive('require')->
            once()->
            with('/project/config.php')->
            andReturn($config);
    }

    protected function expectLocalDirectoryCheck(): void
    {
        $this->
            // First mock
            getMockFile()->
            shouldReceive('isDir')->
            once()->
            with('/local_dir')->
            andReturn(true)->
            // Second mock
            shouldReceive('isWriteable')->
            once()->
            with('/local_dir')->
            andReturn(true);
    }

    protected function expectFtpConnection($port = 21, $timeout = 20): void
    {
        $this->
            getMockFtp()->
            shouldReceive('connect')->
            once()->
            with('ftp.example.com', $port, $timeout)->
            andReturn(true);
    }

    protected function expectFtpLogin(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('login')->
            once()->
            with('example', 'mypassword')->
            andReturn(true);
    }

    protected function expectFtpOptions($pasv = true): void
    {
        $this->
            getMockFtp()->
            shouldReceive('pasv')->
            times($pasv ? 1 : 0)->
            with(true)->
            andReturn(true);
    }

    protected function expectLocalFileListing(array $listing, $filter = '*.log')
    {
        // Let's just quickly verify the supplied listing
        foreach ($listing as $file => $size) {
            if (!is_string($file) || !is_int($size)) {
                throw new RuntimeException(
                    'The required format for expectLocalFileListing is [file:string => size:int, ...]'
                );
            }
        }

        $this->
            getMockFile()->
            shouldReceive('glob')->
            once()->
            with('/local_dir/' . $filter)->
            andReturn(array_keys($listing));

        // There is a filesize call on each file too
        foreach ($listing as $file => $size) {
            $this->
                getMockFile()->
                shouldReceive('filesize')->
                once()->
                with($file)->
                andReturn($size);
        }
    }

    protected function expectRemoteFileListing(array $listing): void
    {
        // Let's just quickly verify the supplied listing
        foreach ($listing as $file) {
            $name = isset($file['name']) ? $file['name'] : null;
            $type = isset($file['type']) ? $file['type'] : null;
            $size = isset($file['size']) ? $file['size'] : null;
            if (!is_string($name) || !is_string($type) || !is_string($size)) {
                throw new RuntimeException(
                    'The required format for expectRemoteFileListing is [[name => string, type => string, size => string], ...]'
                );
            }
        }

        $this->
            getMockFtp()->
            shouldReceive('mlsd')->
            once()->
            with('/remote_dir')->
            andReturn($listing);
    }

    protected function expectFtpChangeDirectory(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('chdir')->
            once()->
            with('/remote_dir')->
            andReturn(true);
    }

    protected function expectFtpGet(array $differences): void
    {
        foreach ($differences as $difference) {
            $this->
                getMockFtp()->
                shouldReceive('get')->
                once()->
                with($difference['path'], $difference['file'], FTP_BINARY)->
                andReturn(true);
        }

        if (!$differences) {
            $this->getMockFtp()->shouldReceive('get')->never();
        }
    }

    protected function expectFtpGetUsingCountsOnly(int $count): void
    {
        $this->
            getMockFtp()->
            shouldReceive('get')->
            times($count)->
            andReturn(true);
    }

    protected function expectFtpCopyOutput(array $files): void
    {
        foreach ($files as $file) {
            $this->
                getMockOutput()->
                shouldReceive('println')->
                once()->
                with("Copy $file OK");
        }

        if (!$files) {
            $this->getMockOutput()->shouldReceive('println')->never();
        }
    }

    protected function expectFtpClose(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('close');
    }

    /**
     * @return File|MockInterface
     */
    protected function getMockFile()
    {
        return $this->file;
    }

    /**
     * @return Ftp|MockInterface
     */
    protected function getMockFtp()
    {
        return $this->ftp;
    }

    /**
     * @return Output|MockInterface
     */
    protected function getMockOutput()
    {
        return $this->output;
    }

    /**
     * @return PhpExtensions|MockInterface
     */
    protected function getMockPhpExtensions()
    {
        return $this->phpExtensions;
    }
}
