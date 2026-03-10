<?php

/**
 * Snippets AddOn - String-Übersetzungen (Sprog-Ersatz)
 *
 * Vereinfachter Modus für mehrsprachige Texte mit Inline-Bearbeitung.
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;
use FriendsOfREDAXO\Snippets\Service\PermissionService;
use FriendsOfREDAXO\Snippets\Service\SnippetsTranslate;

// Berechtigungsprüfung
if (!PermissionService::canTranslate()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

$can_edit = PermissionService::canEdit();
$is_admin = PermissionService::isAdmin();
$csrfToken = rex_csrf_token::factory('snippets_translations');

// POST-Aktionen verarbeiten
$func = rex_request::request('func', 'string', '');

// String hinzufügen
if ('add' === $func && $can_edit) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('snippets_csrf_error'));
    } else {
        $keyName = rex_request::post('key_name', 'string', '');
        $categoryId = rex_request::post('category_id', 'int', 0);

        if ('' === $keyName) {
            echo rex_view::error(rex_i18n::msg('snippets_tstr_error_key_empty'));
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $keyName)) {
            echo rex_view::error(rex_i18n::msg('snippets_error_invalid_key'));
        } elseif (TranslationStringRepository::keyExists($keyName)) {
            echo rex_view::error(rex_i18n::msg('snippets_tstr_error_key_exists'));
        } else {
            $data = ['key_name' => $keyName, 'status' => 1];
            if ($categoryId > 0) {
                $data['category_id'] = $categoryId;
            }

            $newId = TranslationStringRepository::save($data);

            // Werte speichern
            foreach (rex_clang::getAll() as $clang) {
                $value = rex_request::post('value_' . $clang->getId(), 'string', '');
                if ('' !== $value) {
                    TranslationStringRepository::saveValue($newId, $clang->getId(), $value);
                }
            }

            SnippetsTranslate::clearCache();
            echo rex_view::success(rex_i18n::msg('snippets_tstr_added'));
        }
    }
}

// String löschen
if ('delete' === $func && $is_admin) {
    $deleteId = rex_request::request('id', 'int', 0);
    if ($deleteId > 0 && $csrfToken->isValid()) {
        TranslationStringRepository::delete($deleteId);
        SnippetsTranslate::clearCache();
        echo rex_view::success(rex_i18n::msg('snippets_tstr_deleted'));
    }
}

// Inline-Werte speichern (POST von der Liste)
if ('save_inline' === $func && $can_edit) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('snippets_csrf_error'));
    } else {
        $saveId = rex_request::post('string_id', 'int', 0);
        if ($saveId > 0) {
            foreach (rex_clang::getAll() as $clang) {
                $value = rex_request::post('value_' . $saveId . '_' . $clang->getId(), 'string', '');
                TranslationStringRepository::saveValue($saveId, $clang->getId(), $value);
            }
            SnippetsTranslate::clearCache();
            echo rex_view::success(rex_i18n::msg('snippets_tstr_saved'));
        }
    }
}

// Status toggeln
if ('toggle_status' === $func && $can_edit) {
    $toggleId = rex_request::request('id', 'int', 0);
    if ($toggleId > 0) {
        $entity = TranslationStringRepository::getById($toggleId);
        if (null !== $entity) {
            $newStatus = $entity->isActive() ? 0 : 1;
            TranslationStringRepository::save(['id' => $toggleId, 'status' => $newStatus]);
            SnippetsTranslate::clearCache();
            echo rex_view::success(rex_i18n::msg('snippets_status_updated'));
        }
    }
}

// Sprog-Import wurde in die Einstellungsseite verschoben

// Filter
$search = rex_request::get('search', 'string', '');
$category = rex_request::get('category', 'int', 0);
$page = rex_request::get('tstr_page', 'int', 1);
$perPage = 50;

// Kategorien laden
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM ' . rex::getTable('snippets_category') . ' ORDER BY sort_order, name');
$categories = [];
for ($i = 0; $i < $sql->getRows(); ++$i) {
    $catId = $sql->getValue('id');
    if (is_scalar($catId)) {
        $categories[(int) $catId] = [
            'name' => (string) $sql->getValue('name'),
            'icon' => trim((string) $sql->getValue('icon')),
        ];
    }
    $sql->next();
}

// Gesamtanzahl für Paginierung
$filterParams = [
    'search' => $search,
    'category' => $category,
];
$totalCount = TranslationStringRepository::count($filterParams);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

// Strings laden (paginiert)
$strings = TranslationStringRepository::findAll($filterParams + [
    'limit' => $perPage,
    'offset' => $offset,
]);

// Sprachen laden
$clangs = rex_clang::getAll();

// DeepL-Verfügbarkeit prüfen
$deeplAvailable = SnippetsTranslate::isDeeplAvailable();

// Fragment rendern
$fragment = new rex_fragment();
$fragment->setVar('strings', $strings, false);
$fragment->setVar('clangs', $clangs, false);
$fragment->setVar('categories', $categories, false);
$fragment->setVar('can_edit', $can_edit, false);
$fragment->setVar('is_admin', $is_admin, false);
$fragment->setVar('search', $search, false);
$fragment->setVar('category', $category, false);
$fragment->setVar('csrf_token', $csrfToken, false);
$fragment->setVar('deepl_available', $deeplAvailable, false);
$fragment->setVar('source_clang_id', (int) rex_addon::get('snippets')->getConfig('tstr_source_clang_id', rex_clang::getStartId()), false);
$fragment->setVar('current_page', $page, false);
$fragment->setVar('total_pages', $totalPages, false);
$fragment->setVar('total_count', $totalCount, false);
$fragment->setVar('per_page', $perPage, false);

echo $fragment->parse('snippets/translations_listing.php');
