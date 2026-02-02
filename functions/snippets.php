<?php

/**
 * Snippets AddOn - Helper-Funktionen (Legacy-Kompatibilität)
 *
 * Nutze besser: FriendsOfREDAXO\Snippets\Snippets
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Snippets;

function snippet(string $key, array $params = [], ?int $clangId = null, string $context = 'frontend'): string
{
    return Snippets::get($key, $params, $clangId);
}

function snippet_filtered(string $key, array $params = [], array|string $filters = [], ?int $clangId = null): string
{
    return Snippets::filtered($key, $params, $filters, $clangId);
}

function snippet_apply(string $text, ?int $clangId = null, string $context = 'frontend'): string
{
    return Snippets::apply($text, $clangId, $context);
}

function snippet_exists(string $key): bool
{
    return Snippets::exists($key);
}

function snippet_or(string $key, string $fallback, array $params = [], ?int $clangId = null): string
{
    return Snippets::getOr($key, $fallback, $params, $clangId);
}

function snippet_filter(string $content, array|string $filters): string
{
    return Snippets::filter($content, $filters);
}

