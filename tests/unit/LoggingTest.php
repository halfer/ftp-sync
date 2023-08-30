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
        $this->markTestIncomplete();
    }
}
