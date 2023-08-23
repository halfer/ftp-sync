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
        $this->expectLocalFileListing();
        $this->expectRemoteFileListing();
        $this->expectFtpChangeDirectory();
        $this->expectFtpGet();
        $this->expectFtpCopyOutput();
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

    protected function expectConfigFile(): void
    {
        $this->
            // First mock
            getMockFile()->
            shouldReceive('fileExists')->
            with('/project/config.php')->
            andReturn(true)->
            // Second mock
            shouldReceive('require')->
            with('/project/config.php')->
            andReturn([
                'remote_directory' => '/remote_dir',
                'local_directory' => '/local_dir',
                'hostname' => 'ftp.example.com',
                'username' => 'example',
                'password' => 'mypassword',
            ]);
    }

    protected function expectLocalDirectoryCheck(): void
    {
        $this->
            // First mock
            getMockFile()->
            shouldReceive('isDir')->
            with('/local_dir')->
            andReturn(true)->
            // Second mock
            shouldReceive('isWriteable')->
            with('/local_dir')->
            andReturn(true);
    }

    protected function expectFtpConnection(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('connect')->
            with('ftp.example.com', 21, 20)->
            andReturn(true);
    }

    protected function expectFtpLogin(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('login')->
            with('example', 'mypassword')->
            andReturn(true);
    }

    protected function expectFtpOptions(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('pasv')->
            with(true)->
            andReturn(true);
    }

    protected function expectLocalFileListing()
    {
        $listing = [
            '/local_dir/log01.log' => 100,
            '/local_dir/log02.log' => 110,
        ];
        $this->
            getMockFile()->
            shouldReceive('glob')->
            with('/local_dir/*.log')->
            andReturn(array_keys($listing));

        // There is a filesize call on each file too
        foreach ($listing as $file => $size) {
            $this->
                getMockFile()->
                shouldReceive('filesize')->
                with($file)->
                andReturn($size);
        }
    }

    protected function expectRemoteFileListing(): void
    {
        $listing = [
            // Sizes are string ints when they come out of mlsd()
            ['name' => 'log01.log', 'type' => 'file', 'size' => '100', ],
            ['name' => 'log02.log', 'type' => 'file', 'size' => '110', ],
            ['name' => 'log03.log', 'type' => 'file', 'size' => '120', ],
        ];
        $this->
            getMockFtp()->
            shouldReceive('mlsd')->
            with('/remote_dir')->
            andReturn($listing);
    }

    protected function expectFtpChangeDirectory(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('chdir')->
            with('/remote_dir')->
            andReturn(true);
    }

    protected function expectFtpGet(): void
    {
        $this->
            getMockFtp()->
            shouldReceive('get')->
            with('/local_dir/log03.log', 'log03.log', FTP_BINARY)->
            andReturn(true);
    }

    protected function expectFtpCopyOutput(): void
    {
        $this->
            getMockOutput()->
            shouldReceive('println')->
            with('Copy log03.log OK');
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
