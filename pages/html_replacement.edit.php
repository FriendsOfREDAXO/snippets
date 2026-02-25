<?php

/**
 * Snippets AddOn - HTML-Ersetzung bearbeiten
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Domain\HtmlReplacement;
use FriendsOfREDAXO\Snippets\Repository\HtmlReplacementRepository;
use FriendsOfREDAXO\Snippets\Service\PermissionService;

$addon = rex_addon::get('snippets');

// Berechtigungsprüfung
if (!PermissionService::canEdit()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

$csrf = rex_csrf_token::factory('snippets_html_replacement_edit');

// id aus GET (initialer Aufruf) ODER aus POST (nach Formular-Submit, da currentBackendPage() kein id enthält)
$id = rex_request::request('id', 'int', 0);
$replacement = null;

// Bestehende Regel laden
if ($id > 0) {
    $replacement = HtmlReplacementRepository::findById($id);
    if (null === $replacement) {
        echo rex_view::error($addon->i18n('snippets_html_replacement_not_found'));
        return;
    }
}

// Speichern
if (rex_post('save', 'boolean') || rex_post('save_and_close', 'boolean')) {
    if (!$csrf->isValid()) {
        echo rex_view::error($addon->i18n('snippets_csrf_error'));
    } else {
        $name = rex_post('name', 'string', '');
        $description = rex_post('description', 'string', '');
        $type = rex_post('type', 'string', HtmlReplacement::TYPE_CSS_SELECTOR);
        $searchValue = trim(rex_post('search_value', 'string', ''));
        $replacementContent = rex_post('replacement', 'string', '');
        $position = rex_post('position', 'string', HtmlReplacement::POSITION_REPLACE);
        $scopeContext = rex_post('scope_context', 'string', HtmlReplacement::CONTEXT_FRONTEND);
        $priority = rex_post('priority', 'int', 10);
        $status = rex_post('status', 'boolean', true);

        // Scope-Arrays
        $scopeTemplates = rex_post('scope_templates', 'array', []);
        $scopeBackendPages = rex_post('scope_backend_pages', 'array', []);
        $scopeCategories = rex_post('scope_categories', 'array', []);
        $scopeUrlPattern = rex_post('scope_url_pattern', 'string', '');

        // Validierung
        $errors = [];

        if ('' === trim($name)) {
            $errors[] = $addon->i18n('snippets_html_replacement_error_name_empty');
        } elseif (HtmlReplacementRepository::nameExists($name, $id > 0 ? $id : null)) {
            $errors[] = $addon->i18n('snippets_html_replacement_error_name_exists');
        }

        if ('' === trim($searchValue)) {
            $errors[] = $addon->i18n('snippets_html_replacement_error_search_empty');
        }

        if ('' === trim($replacementContent)) {
            $errors[] = $addon->i18n('snippets_html_replacement_error_replacement_empty');
        }

        // Regex-Validierung
        if (HtmlReplacement::TYPE_REGEX === $type) {
            set_error_handler(static function(): bool { return true; });
            $isValidRegex = false !== @preg_match($searchValue, '');
            restore_error_handler();
            
            if (!$isValidRegex) {
                $errors[] = $addon->i18n('snippets_html_replacement_error_invalid_regex');
            }
        }

        if ([] === $errors) {
            $data = [
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'search_value' => $searchValue,
                'replacement' => $replacementContent,
                'position' => $position,
                'scope_context' => $scopeContext,
                'scope_templates' => array_values(array_filter($scopeTemplates, static fn($v): bool => '' !== $v && null !== $v)),
                'scope_backend_pages' => array_values(array_filter($scopeBackendPages, static fn($v): bool => '' !== $v && null !== $v)),
                'scope_categories' => array_values(array_filter($scopeCategories, static fn($v): bool => '' !== $v && null !== $v)),
                'scope_url_pattern' => $scopeUrlPattern,
                'priority' => $priority,
                'status' => $status ? 1 : 0,
            ];

            if ($id > 0) {
                $data['id'] = $id;
            }

            $savedId = HtmlReplacementRepository::save($data);

            if (rex_post('save_and_close', 'boolean')) {
                rex_response::sendRedirect(rex_url::currentBackendPage(['page' => 'snippets/html_replacement']));
            }

            echo rex_view::success($addon->i18n('snippets_html_replacement_saved'));
            
            if (0 === $id) {
                $replacement = HtmlReplacementRepository::findById($savedId);
                $id = $savedId;
            } else {
                $replacement = HtmlReplacementRepository::findById($id);
            }
        } else {
            echo rex_view::error(implode('<br>', $errors));
        }
    }
}

// Template-Auswahl vorbereiten
$templates = [];
$sql = rex_sql::factory();
$sql->setQuery('SELECT id, name FROM ' . rex::getTable('template') . ' ORDER BY name');
foreach ($sql as $row) {
    $templates[(int) $row->getValue('id')] = $row->getValue('name');
}

// Kategorie-Auswahl vorbereiten
$categories = [];
$sql->setQuery('SELECT id, name FROM ' . rex::getTable('article') . ' WHERE startarticle = 1 ORDER BY name');
foreach ($sql as $row) {
    $categories[(int) $row->getValue('id')] = $row->getValue('name');
}

// Backend-Seiten
$backendPages = [
    'structure' => 'Struktur',
    'mediapool' => 'Medienpool',
    'modules' => 'Module',
    'templates' => 'Templates',
    'users' => 'Benutzer',
    'packages' => 'AddOns',
    'system' => 'System',
];

// Formular-Fragment
$formFragment = new rex_fragment();
$formFragment->setVar('id', $id);
$formFragment->setVar('replacement', $replacement);
$formFragment->setVar('csrf_token', $csrf);
$formFragment->setVar('templates', $templates);
$formFragment->setVar('categories', $categories);
$formFragment->setVar('backend_pages', $backendPages);

$content = $formFragment->parse('snippets/html_replacement_form.php');

// Wrapper
$fragment = new rex_fragment();
$fragment->setVar('title', $id > 0 
    ? $addon->i18n('snippets_html_replacement_edit_title') 
    : $addon->i18n('snippets_html_replacement_add_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
