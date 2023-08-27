<?php

namespace FtpSync;

/**
 * A wrapper around stdout functions to make them unit testable.
 */
class Output
{
    public function print(string $message, $exit = false): void
    {
        echo $message;

        if ($exit) {
            exit();
        }
    }

    public function println(string $message, $exit = false): void
    {
        $this->print("$message\n", $exit);
    }
}
