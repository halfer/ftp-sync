<?php

use PHPUnit\Framework\TestCase;

class FailureTest extends TestCase
{
    public function testFailToChangeDirectory()
    {
        $this->markTestIncomplete();
    }

    public function testFailToConnect()
    {
        $this->markTestIncomplete();
    }

    public function testFailToLogin()
    {
        $this->markTestIncomplete();
    }

    public function testFailToSwitchToPassiveMode()
    {
        $this->markTestIncomplete();
    }

    public function testLocalFolderIsNotWriteable()
    {
        $this->markTestIncomplete();
    }

    public function testLocalFolderIsNotFound()
    {
        $this->markTestIncomplete();
    }

    public function testMissingRequiredConfigKey()
    {
        $this->markTestIncomplete();
    }

    public function testFtpExtensionNotLoaded()
    {
        $this->markTestIncomplete();
    }
}
