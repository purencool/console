<?php
namespace App\Services;

use UnexpectedValueException;

/**
 * DirectoryScannerService class.
 *
 * @package App\Services
 */
class DirectoryScannerService
{
    /**
     * Scan all the directories to collate information about
     * it contents so that it can used in chat to discuss.
     *
     * @param string $path
     *
     * @throws UnexpectedValueException
     *
     * @return array{false, string}|array{true, string}
     */
    public static function scan(string $path): array
    {
        $path = trim($path);
        if (!file_exists($path)) {
            return [false, "Path not found: $path"];
        }

        if (is_file($path)) {
            $content = file_get_contents($path);
            $summary = "--- File: $path ---\n" . $content . "\n";
            return [true, $summary];
        }

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $path,
                    \RecursiveDirectoryIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

            $summary = "--- Directory Tree: $path ---\n";
            $fileCount = 0;

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getPathname();
                    // Skip hidden files or vendor directories for sanity
                    if (
                        str_contains($filePath, "/vendor/") ||
                        str_contains($filePath, "/.git/")
                    ) {
                        continue;
                    }
                    $fileCount++;
                    if ($fileCount <= 50) {
                        // Limit to 50 files for token safety
                        $summary .=
                            "\n[File: $filePath]\n" .
                            @file_get_contents($filePath) .
                            "\n";
                    }
                }
            }
            return [true, $summary];
        }

        return [false, "Invalid path type."];
    }
}
