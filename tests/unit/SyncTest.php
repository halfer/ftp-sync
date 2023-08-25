<?php

use PHPUnit\Framework\TestCase;

use FtpSync\File;
use FtpSync\Ftp;
use FtpSync\FtpSync;
use FtpSync\Output;
use Mockery\MockInterface;

class SyncTest extends TestCase
{
    /* @var File $file */
    protected $file;
    /* @var Ftp $ftp */
    protected $ftp;
    /* @var Output $output */
    protected $output;

    public function setUp(): void
    {
        $this->file = Mockery::mock(File::class);
        $this->ftp = Mockery::mock(Ftp::class);
        $this->output = Mockery::mock(Output::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testSimpleFullRun()
    {
        // Expectations
        $this->expectConfigFile();
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing([
            '/local_dir/log01.log' => 100,
            '/local_dir/log02.log' => 110,
        ]);
        $this->expectRemoteFileListing([
            // Sizes are string ints when they come out of mlsd()
            ['name' => 'log01.log', 'type' => 'file', 'size' => '100', ],
            ['name' => 'log02.log', 'type' => 'file', 'size' => '110', ],
            ['name' => 'log03.log', 'type' => 'file', 'size' => '120', ],
        ]);
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet([
            ['path' => '/local_dir/log03.log', 'file' => 'log03.log', ]
        ]);
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
        $this->expectConfigFile();
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection();
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing([
            '/local_dir/log01.log' => 100,
            '/local_dir/log02.log' => 110,
        ]);
        $this->expectRemoteFileListing([
            // Sizes are string ints when they come out of mlsd()
            ['name' => 'log01.log', 'type' => 'file', 'size' => '100', ],
            ['name' => 'log02.log', 'type' => 'file', 'size' => '110', ],
            ['name' => 'log03.txt', 'type' => 'file', 'size' => '120', ], // Not *.log
        ]);
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
        $this->expectConfigFile(['port' => 9999, ]);
        $this->expectLocalDirectoryCheck();
        $this->expectFtpConnection(9999);
        $this->expectFtpLogin();
        $this->expectFtpOptions();
        $this->expectLocalFileListing([
            '/local_dir/log01.log' => 100,
            '/local_dir/log02.log' => 110,
        ]);
        $this->expectRemoteFileListing([
            // Sizes are string ints when they come out of mlsd()
            ['name' => 'log01.log', 'type' => 'file', 'size' => '100', ],
            ['name' => 'log02.log', 'type' => 'file', 'size' => '110', ],
            ['name' => 'log03.log', 'type' => 'file', 'size' => '120', ],
        ]);
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet([
            ['path' => '/local_dir/log03.log', 'file' => 'log03.log', ]
        ]);
        $this->expectFtpCopyOutput(['log03.log']);
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
            '/project',
            ['config' => 'config.php']
        );
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

    protected function expectFtpConnection($port = 21): void
    {
        $this->
            getMockFtp()->
            shouldReceive('connect')->
            once()->
            with('ftp.example.com', $port, 20)->
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

    protected function expectFtpOptions(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('pasv')->
            once()->
            with(true)->
            andReturn(true);
    }

    protected function expectLocalFileListing(array $listing)
    {
        $this->
            getMockFile()->
            shouldReceive('glob')->
            once()->
            with('/local_dir/*.log')->
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
}
