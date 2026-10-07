<?php
namespace App\Support;

use PhpTui\Term\Terminal;
use PhpTui\Term\Actions;

/**
 *
 */
class TerminalSetup
{
    /**
     *
     */
    public static function initialize(): Terminal
    {
        fwrite(STDOUT, "\x1b[?1049h\x1b[2J\x1b[H");
        $terminal = Terminal::new();
        $terminal->enableRawMode();
        $terminal->execute(Actions::enableMouseCapture());

        register_shutdown_function(function () use ($terminal) {
            try {
                $terminal->execute(Actions::disableMouseCapture());
                $terminal->disableRawMode();
            } catch (\Throwable $e) {
            }
            fwrite(STDOUT, "\x1b[?1049l");
        });

        return $terminal;
    }
}
