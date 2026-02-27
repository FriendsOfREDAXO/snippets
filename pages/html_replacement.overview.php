<?php

/**
 * Snippets AddOn - HTML-Ersetzungen Übersicht
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Repository\HtmlReplacementRepository;

$addon = rex_addon::get('snippets');

// Prüfe ob Tabelle existiert
$sql = rex_sql::factory();
try {
    $sql->setQuery('SELECT 1 FROM ' . rex::getTable('snippets_html_replacement') . ' LIMIT 1');
    // Tabelle existiert
} catch (Exception $e) {
    echo rex_view::error('Die Tabelle rex_snippets_html_replacement existiert nicht.<br>Bitte gehe zu <strong>AddOns > Snippets</strong> und klicke auf <strong>Update</strong> oder <strong>Reinstall</strong>.');
    return;
}

$csrf = rex_csrf_token::factory('snippets_html_replacement');

// Aktionen
$func = rex_request::get('func', 'string', '');
$id = rex_request::get('id', 'int', 0);
$msg = '';

// Status Toggle
if ('toggle' === $func && $id > 0) {
    if (!$csrf->isValid()) {
        $msg = rex_view::error($addon->i18n('snippets_csrf_error'));
    } else {
        if (HtmlReplacementRepository::toggleStatus($id)) {
            $msg = rex_view::success($addon->i18n('snippets_html_replacement_status_changed'));
        } else {
            $msg = rex_view::error($addon->i18n('snippets_html_replacement_not_found'));
        }
    }
}

// Löschen
if ('delete' === $func && $id > 0) {
    if (!$csrf->isValid()) {
        $msg = rex_view::error($addon->i18n('snippets_csrf_error'));
    } else {
        if (HtmlReplacementRepository::delete($id)) {
            $msg = rex_view::success($addon->i18n('snippets_html_replacement_deleted'));
        } else {
            $msg = rex_view::error($addon->i18n('snippets_html_replacement_not_found'));
        }
    }
}

echo $msg;

// Liste
$replacements = HtmlReplacementRepository::findAll();
$totalCount = HtmlReplacementRepository::count();
$activeCount = HtmlReplacementRepository::count(true);

// Toolbar
$content = '<section class="rex-page-section">';
$content .= rex_view::info(
    sprintf(
        $addon->i18n('snippets_html_replacement_info'),
        $totalCount,
        $activeCount
    )
);

$content .= '<div class="btn-toolbar">';
$content .= '<a href="' . rex_url::currentBackendPage(['page' => 'snippets/html_replacement/edit']) . '" class="btn btn-primary">';
$content .= '<i class="rex-icon rex-icon-add"></i> ' . $addon->i18n('snippets_html_replacement_add');
$content .= '</a>';
$content .= '</div>';
$content .= '</section>';

// Tabelle
if ([] !== $replacements) {
    $content .= '<section class="rex-page-section">';
    $content .= '<table class="table table-striped table-hover">';
    $content .= '<thead>';
    $content .= '<tr>';
    $content .= '<th class="rex-table-icon"></th>';
    $content .= '<th>' . $addon->i18n('snippets_html_replacement_name') . '</th>';
    $content .= '<th>' . $addon->i18n('snippets_html_replacement_type') . '</th>';
    $content .= '<th>' . $addon->i18n('snippets_html_replacement_scope') . '</th>';
    $content .= '<th class="rex-table-priority">' . $addon->i18n('snippets_html_replacement_priority') . '</th>';
    $content .= '<th class="rex-table-status">' . $addon->i18n('snippets_status') . '</th>';
    $content .= '<th class="rex-table-action" colspan="3">' . $addon->i18n('snippets_functions') . '</th>';
    $content .= '</tr>';
    $content .= '</thead>';
    $content .= '<tbody>';

    foreach ($replacements as $replacement) {
        $replacementId = $replacement->getId();
        
        // Status-Icon
        $statusIcon = $replacement->isActive() 
            ? '<i class="rex-icon rex-icon-active-true"></i>' 
            : '<i class="rex-icon rex-icon-active-false"></i>';
        
        // Typ-Badge
        $typeLabel = match($replacement->getType()) {
            'css_selector' => 'CSS',
            'html_match' => 'HTML',
            'regex' => 'Regex',
            default => $replacement->getType(),
        };
        $typeBadge = '<span class="label label-info">' . rex_escape($typeLabel) . '</span>';
        
        // Scope-Info
        $scopeInfo = [];
        $scopeInfo[] = match($replacement->getScopeContext()) {
            'frontend' => '<i class="rex-icon fa-globe"></i> Frontend',
            'backend' => '<i class="rex-icon fa-cog"></i> Backend',
            'both' => '<i class="rex-icon fa-globe"></i> <i class="rex-icon fa-cog"></i> Beide',
            default => $replacement->getScopeContext(),
        };
        
        if (null !== $replacement->getScopeTemplates() && [] !== $replacement->getScopeTemplates()) {
            $scopeInfo[] = count($replacement->getScopeTemplates()) . ' Template(s)';
        }
        if (null !== $replacement->getScopeCategories() && [] !== $replacement->getScopeCategories()) {
            $scopeInfo[] = count($replacement->getScopeCategories()) . ' Kategorie(n)';
        }
        if (null !== $replacement->getScopeBackendPages() && [] !== $replacement->getScopeBackendPages()) {
            $scopeInfo[] = count($replacement->getScopeBackendPages()) . ' Backend-Seite(n)';
        }
        if (null !== $replacement->getScopeBackendRequestPattern() && '' !== trim($replacement->getScopeBackendRequestPattern())) {
            $scopeInfo[] = 'Request-Pattern';
        }
        
        $scopeDisplay = implode(' • ', $scopeInfo);

        // URLs
        $editUrl = rex_url::currentBackendPage(['page' => 'snippets/html_replacement/edit', 'id' => $replacementId]);
        $toggleUrl = rex_url::currentBackendPage(['func' => 'toggle', 'id' => $replacementId] + $csrf->getUrlParams());
        $deleteUrl = rex_url::currentBackendPage(['func' => 'delete', 'id' => $replacementId] + $csrf->getUrlParams());

        $content .= '<tr>';
        $content .= '<td class="rex-table-icon">' . $statusIcon . '</td>';
        $content .= '<td>';
        $content .= '<strong>' . rex_escape($replacement->getName()) . '</strong>';
        $description = $replacement->getDescription();
        if (null !== $description && '' !== $description) {
            $content .= '<br><small class="text-muted">' . nl2br(rex_escape($description)) . '</small>';
        }
        $content .= '</td>';
        $content .= '<td>' . $typeBadge . '</td>';
        $content .= '<td><small>' . $scopeDisplay . '</small></td>';
        $content .= '<td class="rex-table-priority">' . $replacement->getPriority() . '</td>';
        $content .= '<td class="rex-table-status">';
        if ($replacement->isActive()) {
            $content .= '<span class="label label-success">' . $addon->i18n('snippets_status_active') . '</span>';
        } else {
            $content .= '<span class="label label-default">' . $addon->i18n('snippets_status_inactive') . '</span>';
        }
        $content .= '</td>';
        $content .= '<td class="rex-table-action"><a href="' . $editUrl . '"><i class="rex-icon rex-icon-edit"></i> ' . $addon->i18n('snippets_edit') . '</a></td>';
        $content .= '<td class="rex-table-action"><a href="' . $toggleUrl . '"><i class="rex-icon rex-icon-toggle"></i> ' . $addon->i18n('snippets_toggle_status') . '</a></td>';
        $content .= '<td class="rex-table-action"><a href="' . $deleteUrl . '" data-confirm="' . $addon->i18n('snippets_delete_confirm') . '"><i class="rex-icon rex-icon-delete"></i> ' . $addon->i18n('snippets_delete') . '</a></td>';
        $content .= '</tr>';
    }

    $content .= '</tbody>';
    $content .= '</table>';
    $content .= '</section>';
} else {
    $content .= '<section class="rex-page-section">';
    $content .= rex_view::info($addon->i18n('snippets_html_replacement_no_rules'));
    $content .= '</section>';
}

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('snippets_html_replacement_overview'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
