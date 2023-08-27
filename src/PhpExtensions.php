<?php

namespace FtpSync;

class PhpExtensions
{
    public function extensionLoaded(string $extension): bool
    {
        return extension_loaded($extension);
    }
}
