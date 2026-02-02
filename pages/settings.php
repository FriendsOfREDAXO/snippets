<?php

/**
 * Snippets AddOn - Einstellungen
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\PermissionService;

// Berechtigungsprüfung
if (!PermissionService::isAdmin()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

$addon = rex_addon::get('snippets');
$func = rex_request::request('func', 'string');
$csrfToken = rex_csrf_token::factory('snippets_settings');

// Einstellungen speichern
if ('save' === $func) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $addon->setConfig('php_execution_enabled', rex_request::post('php_execution_enabled', 'bool'));
        $addon->setConfig('sprog_abbrev_enabled', rex_request::post('sprog_abbrev_enabled', 'bool'));
        $addon->setConfig('debug_mode', rex_request::post('debug_mode', 'bool'));
        $addon->setConfig('abbreviation_exclude_selectors', rex_request::post('abbreviation_exclude_selectors', 'string', ''));

        echo rex_view::success(rex_i18n::msg('settings_saved'));
    }
}

// Aktuelle Einstellungen
$phpExecutionEnabled = $addon->getConfig('php_execution_enabled', true);
$sprogAbbrevEnabled = $addon->getConfig('sprog_abbrev_enabled', true);
$debugMode = $addon->getConfig('debug_mode', false);
$abbreviationExcludeSelectors = $addon->getConfig('abbreviation_exclude_selectors', "a\nnav\ncode\npre");

$content = '
<form method="post" action="' . rex_url::currentBackendPage() . '">
    ' . $csrfToken->getHiddenField() . '
    <input type="hidden" name="func" value="save">
    
    <fieldset>
        <legend>' . rex_i18n::msg('snippets_settings_security') . '</legend>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="php_execution_enabled" value="1" ' . ($phpExecutionEnabled ? 'checked' : '') . '>
                ' . rex_i18n::msg('snippets_settings_php_execution') . '
            </label>
            <p class="help-block">' . rex_i18n::msg('snippets_settings_php_execution_notice') . '</p>
        </div>
    </fieldset>';

if (rex_addon::get('sprog')->isAvailable()) {
    $content .= '
    <fieldset>
        <legend>' . rex_i18n::msg('snippets_settings_sprog_integration') . '</legend>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="sprog_abbrev_enabled" value="1" ' . ($sprogAbbrevEnabled ? 'checked' : '') . '>
                ' . rex_i18n::msg('snippets_settings_sprog_abbrev') . '
            </label>
            <p class="help-block">Ermöglicht die Verwendung von [[sprog:key]] über Sprog-Wildcards</p>
        </div>
    </fieldset>';
}

$content .= '
    <fieldset>
        <legend>' . $addon->i18n('abbreviations') . '</legend>
        
        <div class="form-group">
            <label for="abbreviation_exclude_selectors">' . $addon->i18n('abbreviation_exclude_selectors') . '</label>
            <textarea class="form-control" id="abbreviation_exclude_selectors" name="abbreviation_exclude_selectors" rows="10">' . rex_escape($abbreviationExcludeSelectors) . '</textarea>
            <p class="help-block">' . $addon->i18n('abbreviation_exclude_selectors_info') . '</p>
        </div>
    </fieldset>
    
    <fieldset>
        <legend>Debug</legend>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="debug_mode" value="1" ' . ($debugMode ? 'checked' : '') . '>
                Debug-Modus aktivieren
            </label>
            <p class="help-block">Zeigt Fehler bei fehlgeschlagenen Snippet-Ersetzungen als HTML-Kommentare an</p>
        </div>
    </fieldset>
    
    <footer class="panel-footer">
        <div class="rex-form-panel-footer">
            <div class="btn-toolbar">
                <button type="submit" class="btn btn-save rex-form-aligned">
                    <i class="rex-icon rex-icon-save"></i> ' . rex_i18n::msg('snippets_save') . '
                </button>
            </div>
        </div>
    </footer>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('snippets_settings_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Statistiken
$sql = rex_sql::factory();
$sql->setQuery('SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN content_type = "php" THEN 1 ELSE 0 END) as php_snippets
    FROM ' . rex::getTable('snippets_snippet'));

$stats = $sql->getRow();

$statsContent = '
<dl class="dl-horizontal">
    <dt>Gesamt Snippets:</dt>
    <dd>' . (int) ($stats['total'] ?? 0) . '</dd>
    
    <dt>Aktive Snippets:</dt>
    <dd>' . (int) ($stats['active'] ?? 0) . '</dd>
    
    <dt>PHP-Snippets:</dt>
    <dd>' . (int) ($stats['php_snippets'] ?? 0) . '</dd>
</dl>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Statistiken', false);
$fragment->setVar('body', $statsContent, false);
echo $fragment->parse('core/page/section.php');
