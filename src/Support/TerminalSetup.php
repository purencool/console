<?php
namespace App\Support;

use PhpTui\Term\Terminal;
use PhpTui\Term\Actions;

class TerminalSetup
{
    public static function initialize(): Terminal
    {
        // Send all custom terminal modifications (Alternate screen, clear, and mouse tracking protocols)
        // using the exact same stream writer (STDOUT) at the same time to prevent buffering latency.
        // \x1b[?1003h = Track all mouse moves/scrolls | \x1b[?1006h = SGR Extended coordinate format
        fwrite(STDOUT, "\x1b[?1049h\x1b[2J\x1b[H\x1b[?1003h\x1b[?1006h");

        $terminal = Terminal::new();
        $terminal->enableRawMode();

        register_shutdown_function(function () use ($terminal) {
            try {
                $terminal->disableRawMode();
            } catch (\Throwable $e) {
            }
            fwrite(STDOUT, "\x1b[?1003l\x1b[?1006l\x1b[?1049l");
        });

        return $terminal;
    }
}
