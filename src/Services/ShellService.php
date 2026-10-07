<?php
namespace App\Services;

use PhpTui\Term\Terminal;
use PhpTui\Term\Actions;
use ValueError;

/**
 * ShellService
 * @package App\Services
 */
class ShellService
{
    /**
     * SuspendProcess completes the action by suspending the current process.
     * This is useful for temporarily pausing the TUI session and running a
     * command in a separate shell environment.
     *
     * @param Terminal|null $terminal
     * The terminal object to use for executing commands.
     * @param bool $mouseCaptureActive
     *    Whether mouse capture should be active during the subshell execution.
     *    Defaults to false.
     *
     * @throws ValueError
     *
     */
    public static function suspendProcess(
        ?Terminal $terminal,
        bool $mouseCaptureActive,
    ): void {
        if ($terminal) {
            try {
                $terminal->execute(Actions::disableMouseCapture());
                $terminal->disableRawMode();
            } catch (\Throwable $e) {
            }
        }

        fwrite(STDOUT, "\x1b[?1049l");
        shell_exec("stty sane");

        if (function_exists("posix_kill") && function_exists("posix_getpid")) {
            posix_kill(posix_getpid(), SIGTSTP);
        } else {
            exec("kill -TSTP " . getmypid());
        }

        fwrite(STDOUT, "\x1b[?1049h\x1b[2J\x1b[H");
        if ($terminal) {
            $terminal->enableRawMode();
            if ($mouseCaptureActive) {
                $terminal->execute(Actions::enableMouseCapture());
            }
        }
    }

    /**
     * LaunchSubshell completes the action by launching a subshell.
     * This is useful for running commands in a separate shell environment
     * without affecting the main TUI session.
     *
     * @param Terminal|null $terminal
     * The terminal object to use for executing commands.
     * @param bool $mouseCaptureActive Whether mouse capture should be active during the subshell execution. Defaults to false.
     * @throws ValueError
     */
    public static function launchSubshell(
        ?Terminal $terminal,
        bool $mouseCaptureActive,
    ): void {
        if ($terminal) {
            try {
                $terminal->execute(Actions::disableMouseCapture());
                $terminal->disableRawMode();
            } catch (\Throwable $e) {
            }
        }

        fwrite(STDOUT, "\x1b[?1049l");
        shell_exec("stty sane");

        echo "\n[System] Entering interactive subshell. Type 'exit' to return to TUI...\n\n";

        $shell = getenv("SHELL") ?: "/bin/bash";
        $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];

        $process = proc_open($shell, $descriptors, $pipes);
        if (is_resource($process)) {
            proc_close($process);
        }

        fwrite(STDOUT, "\x1b[?1049h\x1b[2J\x1b[H");
        if ($terminal) {
            $terminal->enableRawMode();
            if ($mouseCaptureActive) {
                $terminal->execute(Actions::enableMouseCapture());
            }
        }
    }
}
