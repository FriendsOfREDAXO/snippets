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

// Status ändern
$func = rex_request::get('func', 'string');
$id = rex_request::get('id', 'int');
if ('toggle_status' === $func && $id > 0 && PermissionService::canEdit()) {
    $snippet = SnippetRepository::getById($id);
    if ($snippet) {
        // Status toggeln
        $newStatus = $snippet->isActive() ? 0 : 1;
        SnippetRepository::save(['id' => $id, 'status' => $newStatus]);
        echo rex_view::success(rex_i18n::msg('snippets_status_updated'));
    }
}

// Filter aus Request holen
$search = rex_request::get('search', 'string', '');
$category = rex_request::get('category', 'int', 0);
$context = rex_request::get('context', 'string', '');
$contentType = rex_request::get('content_type', 'string', '');
$category = rex_request::get('category', 'int', 0);

// Kategorien laden für Filter und Anzeige
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM ' . rex::getTable('snippets_category') . ' ORDER BY sort_order, name');
$categories = [];
for ($i = 0; $i < $sql->getRows(); ++$i) {
    $catId = $sql->getValue('id');
    if (is_scalar($catId)) {
        $categories[(int) $catId] = (string) $sql->getValue('name');
    }
    $sql->next();
}

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
$fragment->setVar('categories', $categories, false);
$fragment->setVar('can_edit', PermissionService::canEdit(), false);
$fragment->setVar('can_edit_php', PermissionService::canEditPhp(), false);
$fragment->setVar('search', $search, false);
$fragment->setVar('category', $category, false);
$fragment->setVar('context', $context, false);
$fragment->setVar('content_type', $contentType, false);

echo $fragment->parse('snippets/listing.php');
