<?php

namespace App\Services;

class SnippetService
{
    /**
     * Parses the JSON file defined in .env and extracts snippets and aliases.
     */
    private static function loadSnippetsData(): array
    {
        $path = $_ENV['SNIPPETS_FILE_PATH'] ?? getenv('SNIPPETS_FILE_PATH');
        if (!$path) {
            $path = 'snippets.json'; // Fallback default
        }

        if (!file_exists($path)) {
            return ['snippets' => [], 'aliases' => []];
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);
        
        if (!is_array($data)) {
            return ['snippets' => [], 'aliases' => []];
        }

        $snippets = [];
        $aliases = [];

        foreach ($data as $item) {
            if (isset($item['name'], $item['text'])) {
                $name = strtolower(trim($item['name']));
                $snippets[$name] = $item['text'];
                
                if (isset($item['aliases']) && is_array($item['aliases'])) {
                    foreach ($item['aliases'] as $alias) {
                        $aliases[strtolower(trim($alias))] = $name;
                    }
                }
            }
        }

        return ['snippets' => $snippets, 'aliases' => $aliases];
    }

    public static function get(string $name): ?string
    {
        $data = self::loadSnippetsData();
        $name = strtolower(trim($name));
        
        // Convert alias to full name if it exists
        if (isset($data['aliases'][$name])) {
            $name = $data['aliases'][$name];
        }

        return $data['snippets'][$name] ?? null;
    }

    public static function getAll(): array
    {
        $data = self::loadSnippetsData();
        return $data['snippets'];
    }

    public static function getAvailableList(): array
    {
        $data = self::loadSnippetsData();
        $list = [];
        
        foreach ($data['snippets'] as $key => $text) {
            $aliasList = [];
            foreach ($data['aliases'] as $a => $k) {
                if ($k === $key) {
                    $aliasList[] = $a;
                }
            }
            $aliasStr = !empty($aliasList) ? " (shortcut: " . implode(', ', $aliasList) . ")" : "";
            $list[] = "$key$aliasStr";
        }
        return $list;
    }
}
