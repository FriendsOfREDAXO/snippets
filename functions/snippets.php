<?php

/**
 * Snippets AddOn - Helper-Funktionen
 *
 * Globale Funktionen für einfachen Zugriff auf Snippets.
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\SnippetService;
use FriendsOfREDAXO\Snippets\Service\ReplacementService;
use FriendsOfREDAXO\Snippets\Service\FilterService;
use FriendsOfREDAXO\Snippets\Util\Parser;

/**
 * Parst Filter-Strings zu einem Array für FilterService.
 *
 * @param array<string>|string $filters Filter als Array oder Pipe-getrennt
 * @return array<int, array{name: string, args: array<int, string>}>
 */
function _snippet_parse_filters(array|string $filters): array
{
    // Filters als Array normalisieren
    if (is_string($filters)) {
        $filters = explode('|', $filters);
    }

    if ([] === $filters) {
        return [];
    }

    $parsedFilters = [];
    foreach ($filters as $filterStr) {
        $filterStr = trim($filterStr);
        if ('' === $filterStr) {
            continue;
        }

        // Filter mit Argumenten: name(arg1, arg2)
        if (1 === preg_match('/^(\w+)\((.+)\)$/', $filterStr, $match)) {
            $name = strtolower($match[1]);
            $args = array_map('trim', explode(',', $match[2]));
            // Anführungszeichen entfernen
            $args = array_map(static fn($a) => trim($a, '"\''), $args);
            $parsedFilters[] = ['name' => $name, 'args' => $args];
        } else {
            // Filter ohne Argumente
            $parsedFilters[] = ['name' => strtolower($filterStr), 'args' => []];
        }
    }

    return $parsedFilters;
}

/**
 * Rendert ein einzelnes Snippet.
 *
 * @param string $key Snippet-Key
 * @param array<string, mixed> $params Parameter für das Snippet
 * @param int|null $clangId Sprach-ID (null = aktuelle Sprache)
 * @param string $context Context ('frontend' oder 'backend')
 * @return string Gerenderter Snippet-Inhalt
 *
 * @example
 * // Einfaches Snippet
 * echo snippet('footer_text');
 *
 * // Mit Parametern
 * echo snippet('greeting', ['name' => 'Max']);
 *
 * // Für andere Sprache
 * echo snippet('headline', [], 2);
 */
function snippet(string $key, array $params = [], ?int $clangId = null, string $context = 'frontend'): string
{
    $clangId ??= rex_clang::getCurrentId();

    try {
        return SnippetService::render($key, $params, $clangId, $context);
    } catch (\Exception $e) {
        if (rex::isDebugMode()) {
            return '<!-- Snippet Error: ' . rex_escape($key) . ' - ' . rex_escape($e->getMessage()) . ' -->';
        }
        return '';
    }
}

/**
 * Rendert ein Snippet mit Filtern.
 *
 * @param string $key Snippet-Key
 * @param array<string, mixed> $params Parameter für das Snippet
 * @param array<string>|string $filters Filter als Array oder Pipe-getrennt
 * @param int|null $clangId Sprach-ID (null = aktuelle Sprache)
 * @return string Gerenderter und gefilterter Snippet-Inhalt
 *
 * @example
 * // Mit einzelnem Filter
 * echo snippet_filtered('headline', [], 'upper');
 *
 * // Mit mehreren Filtern (Array)
 * echo snippet_filtered('description', [], ['truncate(100,...)', 'strip_tags']);
 *
 * // Mit Filtern als String (Pipe-getrennt)
 * echo snippet_filtered('content', [], 'markdown|sanitize');
 */
function snippet_filtered(string $key, array $params = [], array|string $filters = [], ?int $clangId = null): string
{
    $content = snippet($key, $params, $clangId);

    if ('' === $content) {
        return $content;
    }

    $parsedFilters = _snippet_parse_filters($filters);

    if ([] === $parsedFilters) {
        return $content;
    }

    return FilterService::apply($content, $parsedFilters);
}

/**
 * Ersetzt alle Snippet-Platzhalter im Text.
 *
 * @param string $text Text mit Snippet-Platzhaltern
 * @param int|null $clangId Sprach-ID (null = aktuelle Sprache)
 * @param string $context Context ('frontend' oder 'backend')
 * @return string Text mit ersetzten Snippets
 *
 * @example
 * // Text mit Snippets ersetzen
 * $html = '<h1>[[snippet:headline]]</h1><p>[[snippet:intro|upper]]</p>';
 * echo snippet_apply($html);
 *
 * // Für andere Sprache
 * echo snippet_apply($template, 2);
 *
 * // Aus Datei
 * $template = rex_file::get(rex_path::addon('myAddon', 'templates/mail.html'));
 * echo snippet_apply($template);
 */
function snippet_apply(string $text, ?int $clangId = null, string $context = 'frontend'): string
{
    $clangId ??= rex_clang::getCurrentId();

    return ReplacementService::replace($text, [
        'clang_id' => $clangId,
        'context' => $context,
    ]);
}

/**
 * Prüft ob ein Snippet existiert und aktiv ist.
 *
 * @param string $key Snippet-Key
 * @return bool True wenn Snippet existiert und aktiv ist
 *
 * @example
 * if (snippet_exists('special_offer')) {
 *     echo snippet('special_offer');
 * }
 */
function snippet_exists(string $key): bool
{
    $snippet = \FriendsOfREDAXO\Snippets\Repository\SnippetRepository::getByKey($key);
    return null !== $snippet && $snippet->isActive();
}

/**
 * Gibt ein Snippet zurück oder einen Fallback-Wert.
 *
 * @param string $key Snippet-Key
 * @param string $fallback Fallback-Wert wenn Snippet nicht existiert oder leer
 * @param array<string, mixed> $params Parameter für das Snippet
 * @param int|null $clangId Sprach-ID (null = aktuelle Sprache)
 * @return string Snippet-Inhalt oder Fallback
 *
 * @example
 * // Mit Fallback
 * echo snippet_or('headline', 'Willkommen');
 *
 * // Mit Parametern
 * echo snippet_or('greeting', 'Hallo!', ['name' => 'Gast']);
 */
function snippet_or(string $key, string $fallback, array $params = [], ?int $clangId = null): string
{
    $content = snippet($key, $params, $clangId);

    if ('' === trim($content)) {
        return $fallback;
    }

    return $content;
}

/**
 * Wendet Filter auf einen beliebigen String an.
 *
 * @param string $content Der zu filternde Inhalt
 * @param array<string>|string $filters Filter als Array oder Pipe-getrennt
 * @return string Gefilterter Inhalt
 *
 * @example
 * // Text filtern
 * echo snippet_filter('Mein langer Text hier...', 'truncate(20,...)|upper');
 *
 * // Mehrere Filter
 * echo snippet_filter($content, ['markdown', 'sanitize', 'nl2br']);
 */
function snippet_filter(string $content, array|string $filters): string
{
    if ('' === $content) {
        return $content;
    }

    $parsedFilters = _snippet_parse_filters($filters);

    if ([] === $parsedFilters) {
        return $content;
    }

    return FilterService::apply($content, $parsedFilters);
}
