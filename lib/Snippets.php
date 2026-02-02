<?php

/**
 * Snippets AddOn - Kurzform-API
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets;

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Service\FilterService;
use FriendsOfREDAXO\Snippets\Service\ReplacementService;
use FriendsOfREDAXO\Snippets\Service\SnippetService;
use rex;
use rex_clang;

/**
 * Kurzform-API für Snippets
 */
class Snippets
{
    /** Rendert ein Snippet */
    public static function get(string $key, array $params = [], ?int $clangId = null): string
    {
        try {
            return SnippetService::render($key, $params, $clangId ?? rex_clang::getCurrentId());
        } catch (\Exception $e) {
            return rex::isDebugMode() ? "<!-- Snippet: {$key} - {$e->getMessage()} -->" : '';
        }
    }

    /** Rendert Snippet mit Filtern */
    public static function filtered(string $key, array $params = [], array|string $filters = [], ?int $clangId = null): string
    {
        $content = self::get($key, $params, $clangId);
        return '' !== $content && [] !== ($f = self::parseFilters($filters)) ? FilterService::apply($content, $f) : $content;
    }

    /** Ersetzt Snippet-Platzhalter im Text */
    public static function apply(string $text, ?int $clangId = null, string $context = 'frontend'): string
    {
        return ReplacementService::replace($text, ['clang_id' => $clangId ?? rex_clang::getCurrentId(), 'context' => $context]);
    }

    /** Prüft ob Snippet existiert und aktiv ist */
    public static function exists(string $key): bool
    {
        $s = SnippetRepository::getByKey($key);
        return null !== $s && $s->isActive();
    }

    /** Gibt Snippet oder Fallback zurück */
    public static function getOr(string $key, string $fallback, array $params = [], ?int $clangId = null): string
    {
        $c = self::get($key, $params, $clangId);
        return '' !== trim($c) ? $c : $fallback;
    }

    /** Wendet Filter auf String an */
    public static function filter(string $content, array|string $filters): string
    {
        return '' !== $content && [] !== ($f = self::parseFilters($filters)) ? FilterService::apply($content, $f) : $content;
    }

    /** @return array<int, array{name: string, args: array<int, string>}> */
    private static function parseFilters(array|string $filters): array
    {
        $filters = is_string($filters) ? explode('|', $filters) : $filters;
        $result = [];
        foreach ($filters as $f) {
            $f = trim($f);
            if ('' === $f) continue;
            if (preg_match('/^(\w+)\((.+)\)$/', $f, $m)) {
                $result[] = ['name' => strtolower($m[1]), 'args' => array_map(static fn($a) => trim($a, '"\' '), explode(',', $m[2]))];
            } else {
                $result[] = ['name' => strtolower($f), 'args' => []];
            }
        }
        return $result;
    }
}
