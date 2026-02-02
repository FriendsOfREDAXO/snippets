<?php

/**
 * Snippets AddOn - Abkürzungen Übersicht
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Domain\Abbreviation;
use FriendsOfREDAXO\Snippets\Repository\AbbreviationRepository;

$addon = rex_addon::get('snippets');

// Actions - nur delete und status, add/edit werden von edit.php behandelt
$func = rex_request('func', 'string', '');
$id = rex_request('id', 'int', 0);

// Löschen
if ($func === 'delete' && $id > 0) {
    if (AbbreviationRepository::delete($id)) {
        echo rex_view::success($addon->i18n('abbreviation_deleted'));
    } else {
        echo rex_view::error($addon->i18n('abbreviation_delete_failed'));
    }
}

// Status ändern
if ($func === 'status' && $id > 0) {
    if (AbbreviationRepository::toggleStatus($id)) {
        echo rex_view::success($addon->i18n('abbreviation_status_updated'));
    } else {
        echo rex_view::error($addon->i18n('abbreviation_status_update_failed'));
    }
}

// Liste laden
$abbreviations = AbbreviationRepository::findAll();

// Toolbar mit Add-Button
echo '<div class="row">';
echo '<div class="col-lg-12">';
echo '<h2 class="rex-page-header">' . $addon->i18n('abbreviations_overview') . '</h2>';
echo '<div class="rex-toolbar">';
echo '<div class="rex-toolbar-content">';
echo '<a href="' . rex_url::currentBackendPage(['page' => 'snippets/abbreviations/edit']) . '" class="btn btn-primary">';
echo '<i class="rex-icon rex-icon-add"></i> ' . $addon->i18n('abbreviation_add');
echo '</a>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Tabelle
echo '<section class="rex-page-section">';
echo '<div class="panel panel-default">';
echo '<header class="panel-heading"><div class="panel-title">' . $addon->i18n('abbreviations_list') . '</div></header>';
echo '<div class="panel-body-wrapper">';

$content = '
<table class="table table-striped table-hover">
    <thead>
        <tr>
            <th class="rex-table-icon"><i class="rex-icon rex-icon-anchor"></i></th>
            <th>' . $addon->i18n('abbreviation_abbr') . '</th>
            <th>' . $addon->i18n('abbreviation_title') . '</th>
            <th>' . $addon->i18n('abbreviation_language') . '</th>
            <th>' . $addon->i18n('abbreviation_options') . '</th>
            <th>' . $addon->i18n('abbreviation_priority') . '</th>
            <th>' . $addon->i18n('abbreviation_status') . '</th>
            <th class="rex-table-action" colspan="3">' . $addon->i18n('abbreviation_functions') . '</th>
        </tr>
    </thead>
    <tbody>
';

if ([] === $abbreviations) {
    $content .= '
        <tr>
            <td colspan="9" class="text-center">' . $addon->i18n('abbreviations_empty') . '</td>
        </tr>
    ';
} else {
    foreach ($abbreviations as $abbreviation) {
        $abbrId = $abbreviation->getId();
        $isActive = $abbreviation->isActive();
        
        // Status Label
        $statusLabel = $isActive
            ? '<span class="label label-success">' . $addon->i18n('status_active') . '</span>'
            : '<span class="label label-default">' . $addon->i18n('status_inactive') . '</span>';
        
        // Sprache
        $language = $abbreviation->getLanguage();
        $languageLabel = null !== $language && $language > 0
            ? (rex_clang::get($language)?->getName() ?? $addon->i18n('abbreviation_language_all'))
            : $addon->i18n('abbreviation_language_all');
        
        // Optionen
        $options = [];
        if ($abbreviation->isCaseSensitive()) {
            $options[] = $addon->i18n('abbreviation_case_sensitive');
        }
        if ($abbreviation->isWholeWord()) {
            $options[] = $addon->i18n('abbreviation_whole_word');
        }
        $optionsStr = [] !== $options ? implode(', ', $options) : '-';
        
        // Status Toggle
        $statusIcon = $isActive ? 'circle' : 'circle-o';
        $statusTitle = $isActive 
            ? $addon->i18n('abbreviation_status_deactivate')
            : $addon->i18n('abbreviation_status_activate');
        
        $content .= '
        <tr>
            <td class="rex-table-icon"><i class="rex-icon fa-abbreviation"></i></td>
            <td><code>' . rex_escape($abbreviation->getAbbr()) . '</code></td>
            <td>' . rex_escape($abbreviation->getTitle()) . '</td>
            <td>' . rex_escape($languageLabel) . '</td>
            <td>' . rex_escape($optionsStr) . '</td>
            <td>' . $abbreviation->getPriority() . '</td>
            <td>' . $statusLabel . '</td>
            <td class="rex-table-action">
                <a href="' . rex_url::currentBackendPage(['page' => 'snippets/abbreviations/edit', 'id' => $abbrId]) . '" title="' . $addon->i18n('abbreviation_edit') . '">
                    <i class="rex-icon rex-icon-edit"></i>
                </a>
            </td>
            <td class="rex-table-action">
                <a href="' . rex_url::backendPage('snippets/abbreviations/overview', ['func' => 'status', 'id' => $abbrId]) . '" title="' . $statusTitle . '">
                    <i class="rex-icon fa-' . $statusIcon . '"></i>
                </a>
            </td>
            <td class="rex-table-action">
                <a href="' . rex_url::backendPage('snippets/abbreviations/overview', ['func' => 'delete', 'id' => $abbrId]) . '" 
                   data-confirm="' . $addon->i18n('abbreviation_delete_confirm') . '" 
                   title="' . $addon->i18n('abbreviation_delete') . '">
                    <i class="rex-icon rex-icon-delete"></i>
                </a>
            </td>
        </tr>
        ';
    }
}

$content .= '
    </tbody>
</table>
';

echo $content;
echo '</div>';
echo '</div>';
echo '</section>';
