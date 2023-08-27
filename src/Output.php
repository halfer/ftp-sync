<?php

namespace FtpSync;

/**
 * A wrapper around stdout functions to make them unit testable.
 */
class Output
{
    public function print(string $message, ?int $exitCode = null): void
    {
        echo $message;

        if (!is_null($exitCode)) {
            exit($exitCode);
        }
    }

    public function println(string $message, ?int $exitCode = null): void
    {
        $this->print("$message\n", $exitCode);
    }
}
