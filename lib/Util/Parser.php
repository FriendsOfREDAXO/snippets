<?php

namespace FriendsOfREDAXO\Snippets\Util;

use FriendsOfREDAXO\Snippets\Service\FilterService;

/**
 * Parser für Snippet-Platzhalter
 *
 * Syntax: [[snippet:key_name]]
 * Mit Parametern: [[snippet:key_name|param1=value|param2=value]]
 * Mit Filtern: [[snippet:key_name|upper|limit(100,...)]]
 * Kombiniert: [[snippet:key_name|upper|param=value|truncate(50)]]
 *
 * Filter werden erkannt durch:
 * - Kein "=" im String (keine Parameter-Zuweisung)
 * - Optionale Klammern für Argumente: filter(arg1,arg2)
 *
 * @package redaxo\snippets
 */
class Parser
{
    /**
     * Pattern für Snippet-Platzhalter
     */
    private const PATTERN = '/\[\[snippet:([\w\-_]+)(?:\|([^\]]+))?\]\]/';

    /**
     * Findet alle Snippets im Content
     *
     * @return array<int, array{key: string, params: array<string, string>, filters: array<int, array{name: string, args: array<int, string>}>, full_match: string}>
     */
    public static function findAll(string $content): array
    {
        $matches = [];
        if (preg_match_all(self::PATTERN, $content, $pregMatches, PREG_SET_ORDER)) {
            foreach ($pregMatches as $match) {
                $parsed = self::parseModifiers($match[2] ?? '');
                $matches[] = [
                    'key' => $match[1],
                    'params' => $parsed['params'],
                    'filters' => $parsed['filters'],
                    'full_match' => $match[0],
                ];
            }
        }

        return $matches;
    }

    /**
     * Parst Modifiers (Parameter und Filter) aus dem String
     *
     * @return array{params: array<string, string>, filters: array<int, array{name: string, args: array<int, string>}>}
     */
    public static function parseModifiers(string $modifierString): array
    {
        $params = [];
        $filters = [];

        if ('' === $modifierString) {
            return ['params' => $params, 'filters' => $filters];
        }

        // Teile bei | aber nicht innerhalb von Klammern
        $parts = self::splitByPipe($modifierString);

        foreach ($parts as $part) {
            $part = trim($part);

            if ('' === $part) {
                continue;
            }

            // Parameter: enthält "=" ohne vorangehende Klammer
            if (str_contains($part, '=') && !preg_match('/^\w+\(/', $part)) {
                [$key, $value] = explode('=', $part, 2);
                $params[trim($key)] = trim($value);
                continue;
            }

            // Filter: Name mit optionalen Argumenten in Klammern
            $filter = self::parseFilter($part);
            if (null !== $filter) {
                $filters[] = $filter;
            }
        }

        return ['params' => $params, 'filters' => $filters];
    }

    /**
     * Parst einen einzelnen Filter
     *
     * @return array{name: string, args: array<int, string>}|null
     */
    private static function parseFilter(string $filterString): ?array
    {
        // Filter mit Argumenten: name(arg1, arg2, ...)
        if (preg_match('/^(\w+)\((.+)\)$/', $filterString, $match)) {
            $name = strtolower($match[1]);

            // Prüfen ob Filter existiert
            if (!FilterService::exists($name)) {
                return null;
            }

            // Argumente parsen - beachte Strings mit Kommas
            $args = self::parseFilterArgs($match[2]);

            return ['name' => $name, 'args' => $args];
        }

        // Filter ohne Argumente: nur name
        if (preg_match('/^(\w+)$/', $filterString, $match)) {
            $name = strtolower($match[1]);

            // Prüfen ob Filter existiert
            if (!FilterService::exists($name)) {
                return null;
            }

            return ['name' => $name, 'args' => []];
        }

        return null;
    }

    /**
     * Parst Filter-Argumente (kommagetrennt, berücksichtigt Anführungszeichen)
     *
     * @return array<int, string>
     */
    private static function parseFilterArgs(string $argsString): array
    {
        $args = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';
        $depth = 0;

        for ($i = 0; $i < strlen($argsString); $i++) {
            $char = $argsString[$i];

            // Anführungszeichen tracken
            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                    continue; // Anführungszeichen nicht ins Ergebnis
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = '';
                    continue; // Anführungszeichen nicht ins Ergebnis
                }
            }

            // Klammern tracken (für verschachtelte Strukturen)
            if (!$inQuotes) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
            }

            // Komma als Trenner (nur außerhalb von Anführungszeichen und Klammern)
            if ($char === ',' && !$inQuotes && $depth === 0) {
                $args[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Letztes Argument
        if ('' !== trim($current)) {
            $args[] = trim($current);
        }

        return $args;
    }

    /**
     * Teilt String bei | aber nicht innerhalb von Klammern
     *
     * @return array<int, string>
     */
    private static function splitByPipe(string $string): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];

            // Anführungszeichen tracken
            if (($char === '"' || $char === "'") && ($i === 0 || $string[$i - 1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = '';
                }
            }

            // Klammern tracken
            if (!$inQuotes) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
            }

            // Pipe als Trenner
            if ($char === '|' && !$inQuotes && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Letzter Teil
        $parts[] = $current;

        return $parts;
    }

    /**
     * Parst Parameter aus dem Format "key1=value1|key2=value2" (Legacy)
     *
     * @return array<string, string>
     * @deprecated Nutze parseModifiers() stattdessen
     */
    public static function parseParams(string $paramString): array
    {
        $result = self::parseModifiers($paramString);
        return $result['params'];
    }

    /**
     * Validiert einen Snippet-Key
     */
    public static function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9\-_]+$/i', $key);
    }
}
