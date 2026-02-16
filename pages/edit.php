<?php

/**
 * Snippets AddOn - Edit-Seite
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Service\PermissionService;
use FriendsOfREDAXO\Snippets\Util\Parser;

// Berechtigungsprüfung
if (!PermissionService::canEdit()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

$func = rex_request::request('func', 'string');
$id = rex_request::request('id', 'int');
$csrfToken = rex_csrf_token::factory('snippets_edit');

// Aktion ausführen
if ('save' === $func) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $key = rex_request::post('key_name', 'string');
        $title = rex_request::post('title', 'string');
        $description = rex_request::post('description', 'string');
        $content = rex_request::post('content', 'string');
        $contentType = rex_request::post('content_type', 'string');
        $context = rex_request::post('context', 'string');
        $status = rex_request::post('status', 'int', 0);
        $categoryId = rex_request::post('category_id', 'int', 0);
        $isMultilang = rex_request::post('is_multilang', 'int', 0);
        $htmlSelector = rex_request::post('html_selector', 'string');
        $htmlPosition = rex_request::post('html_position', 'string', 'replace');

        $errors = [];

        // Validierung
        if ('' === $key) {
            $errors[] = rex_i18n::msg('snippets_form_key') . ': ' . rex_i18n::msg('snippets_required');
        } elseif (!Parser::isValidKey($key)) {
            $errors[] = rex_i18n::msg('snippets_error_invalid_key');
        }

        if ('' === $title) {
            $errors[] = rex_i18n::msg('snippets_form_title') . ': ' . rex_i18n::msg('snippets_required');
        }

        // Key-Eindeutigkeit prüfen
        $existingSnippet = SnippetRepository::getByKey($key);
        if (null !== $existingSnippet && (0 === $id || $existingSnippet->getId() !== $id)) {
            $errors[] = rex_i18n::msg('snippets_error_key_exists');
        }

        // PHP-Berechtigung prüfen
        if ('php' === $contentType && !PermissionService::canEditPhp()) {
            $errors[] = rex_i18n::msg('snippets_error_php_permission');
        }

        if ([] === $errors) {
            $data = [
                'key_name' => $key,
                'title' => $title,
                'description' => $description,
                'content' => $content,
                'content_type' => $contentType,
                'context' => $context,
                'status' => $status,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'is_multilang' => $isMultilang,
                'html_mode' => 'html_replace' === $contentType ? 'selector' : null,
                'html_selector' => 'html_replace' === $contentType ? $htmlSelector : null,
                'html_position' => 'html_replace' === $contentType ? $htmlPosition : null,
            ];

            if ($id > 0) {
                $data['id'] = $id;
            }

            try {
                $savedId = SnippetRepository::save($data);

                echo rex_view::success(rex_i18n::msg('snippets_saved'));

                // Zurück zur Übersicht oder weiter bearbeiten
                if (rex_request::post('save_and_close', 'bool')) {
                    rex_response::sendRedirect(rex_url::backendPage('snippets/overview'));
                }

                $id = $savedId;
                $func = 'edit'; // Wichtig: Modus auf edit setzen
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ([] !== $errors) {
            echo rex_view::error(implode('<br>', $errors));
        }
    }
} elseif ('delete' === $func && $id > 0) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        try {
            SnippetRepository::delete($id);
            echo rex_view::success(rex_i18n::msg('snippets_deleted'));
            rex_response::sendRedirect(rex_url::backendPage('snippets/overview'));
        } catch (Exception $e) {
            echo rex_view::error($e->getMessage());
        }
    }
}

// Snippet laden für Edit-Modus
$snippet = null;
if ('edit' === $func && $id > 0) {
    $snippet = SnippetRepository::getById($id);
    if (null === $snippet) {
        echo rex_view::error(rex_i18n::msg('snippet_not_found'));
        return;
    }

    // PHP-Berechtigung prüfen
    if ('php' === $snippet->getContentType() && !PermissionService::canEditPhp()) {
        echo rex_view::error(rex_i18n::msg('snippets_error_php_permission'));
        return;
    }
}

// Kategorien laden
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

// Wenn Snippet geladen und System mehrsprachig, zeige Tabs
if (null !== $snippet && rex_clang::count() > 1 && $id > 0) {
    // Tab-Navigation
    $currentTab = rex_request::get('tab', 'string', 'main');
    
    $tabs = '<ul class="nav nav-tabs" role="tablist">';
    $tabs .= '<li role="presentation"' . ('main' === $currentTab ? ' class="active"' : '') . '>';
    $tabs .= '<a href="' . rex_url::currentBackendPage(['func' => 'edit', 'id' => $id, 'tab' => 'main']) . '">';
    $tabs .= rex_i18n::msg('snippets_tab_main');
    $tabs .= '</a></li>';
    
    $tabs .= '<li role="presentation"' . ('translations' === $currentTab ? ' class="active"' : '') . '>';
    $tabs .= '<a href="' . rex_url::currentBackendPage(['func' => 'edit', 'id' => $id, 'tab' => 'translations']) . '">';
    $tabs .= rex_i18n::msg('snippets_tab_translations');
    $tabs .= '</a></li>';
    $tabs .= '</ul>';
    
    echo $tabs;
    
    if ('translations' === $currentTab) {
        // Übersetzungs-Fragment
        $fragment = new rex_fragment();
        $fragment->setVar('snippet', $snippet, false);
        $fragment->setVar('csrf_token', $csrfToken, false);
        echo $fragment->parse('snippets/translations.php');
    } else {
        // Haupt-Formular
        $fragment = new rex_fragment();
        $fragment->setVar('snippet', $snippet, false);
        $fragment->setVar('categories', $categories, false);
        $fragment->setVar('csrf_token', $csrfToken, false);
        $fragment->setVar('can_edit_php', PermissionService::canEditPhp(), false);
        echo $fragment->parse('snippets/edit_form.php');
    }
} else {
    // Kein mehrsprachiges System oder neues Snippet
    $fragment = new rex_fragment();
    $fragment->setVar('snippet', $snippet, false);
    $fragment->setVar('categories', $categories, false);
    $fragment->setVar('csrf_token', $csrfToken, false);
    $fragment->setVar('can_edit_php', PermissionService::canEditPhp(), false);
    echo $fragment->parse('snippets/edit_form.php');
}
