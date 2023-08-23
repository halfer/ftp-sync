<?php

namespace FtpSync;

/**
 * A wrapper around stdout functions to make them unit testable.
 */
class Output
{
    public function print(string $message): void
    {
        echo $message;
    }

    public function println(string $message): void
    {
        echo "$message\n";
    }
}
