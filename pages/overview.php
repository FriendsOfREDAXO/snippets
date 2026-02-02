<?php

/**
 * Snippets AddOn - Übersichtsseite
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Service\PermissionService;

// Berechtigungsprüfung
if (!PermissionService::canView()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

// Filter aus Request holen
$search = rex_request::get('search', 'string', '');
$category = rex_request::get('category', 'int', 0);
$context = rex_request::get('context', 'string', '');
$contentType = rex_request::get('content_type', 'string', '');

// Snippets laden
$snippets = SnippetRepository::findAll([
    'search' => $search,
    'category' => $category,
    'context' => $context,
    'content_type' => $contentType,
]);

// Fragment für Listing
$fragment = new rex_fragment();
$fragment->setVar('snippets', $snippets, false);
$fragment->setVar('can_edit', PermissionService::canEdit(), false);
$fragment->setVar('can_edit_php', PermissionService::canEditPhp(), false);
$fragment->setVar('search', $search, false);
$fragment->setVar('category', $category, false);
$fragment->setVar('context', $context, false);
$fragment->setVar('content_type', $contentType, false);

echo $fragment->parse('snippets/listing.php');
