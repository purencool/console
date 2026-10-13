<?php
namespace App\Services;

class SnippetService
{
    /** @var array|null Memory cache to prevent repetitive, slow disk lookups */
    private static ?array $cachedData = null;

    /**
     * Parses the JSON file defined in .env and extracts snippets and aliases.
     */
    private static function loadSnippetsData(): array
    {
        // Return the memory cache instantly if it's already loaded
        if (self::$cachedData !== null) {
            return self::$cachedData;
        }

        $path = $_ENV["SNIPPETS_PATH"] ?? getenv("SNIPPETS_PATH");
        if (!$path) {
            // Resolve path relative to project root, using your standard hidden '.config' directory!
            $path = dirname(__DIR__, 2) . "/.config/snippets.json";
        } else {
            // Ensure any relative paths from .env are evaluated from the root safely
            if (!str_starts_with($path, "/")) {
                $path = dirname(__DIR__, 2) . "/" . ltrim($path, "/");
            }
        }

        if (!file_exists($path)) {
            self::$cachedData = ["snippets" => [], "aliases" => []];
            return self::$cachedData;
        }

        $json = @file_get_contents($path);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            self::$cachedData = ["snippets" => [], "aliases" => []];
            return self::$cachedData;
        }

        $snippets = [];
        $aliases = [];

        foreach ($data as $item) {
            if (isset($item["name"], $item["text"])) {
                $name = strtolower(trim($item["name"]));
                $snippets[$name] = $item["text"];

                if (isset($item["aliases"]) && is_array($item["aliases"])) {
                    foreach ($item["aliases"] as $alias) {
                        $aliases[strtolower(trim($alias))] = $name;
                    }
                }
            }
        }

        self::$cachedData = ["snippets" => $snippets, "aliases" => $aliases];
        return self::$cachedData;
    }

    /**
     * Clear memory cache (useful if snippets file is updated at runtime)
     */
    public static function clearCache(): void
    {
        self::$cachedData = null;
    }

    public static function get(string $name): ?string
    {
        $data = self::loadSnippetsData();
        $name = strtolower(trim($name));

        // Convert alias to full name if it exists
        if (isset($data["aliases"][$name])) {
            $name = $data["aliases"][$name];
        }

        return $data["snippets"][$name] ?? null;
    }

    public static function getAll(): array
    {
        $data = self::loadSnippetsData();
        return $data["snippets"];
    }

    public static function getAvailableList(): array
    {
        $data = self::loadSnippetsData();
        $list = [];

        foreach ($data["snippets"] as $key => $text) {
            $aliasList = [];
            foreach ($data["aliases"] as $a => $k) {
                if ($k === $key) {
                    $aliasList[] = $a;
                }
            }
            $aliasStr = !empty($aliasList)
                ? " (shortcut: " . implode(", ", $aliasList) . ")"
                : "";
            $list[] = "$key$aliasStr";
        }
        return $list;
    }
}
