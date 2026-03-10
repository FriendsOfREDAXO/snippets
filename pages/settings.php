<?php

/**
 * Snippets AddOn - Einstellungen
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\PermissionService;
use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;
use FriendsOfREDAXO\Snippets\Service\SnippetsTranslate;

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
        $addon->setConfig('html_replacement_allow_snippets', rex_request::post('html_replacement_allow_snippets', 'bool'));
        $addon->setConfig('abbreviation_exclude_selectors', rex_request::post('abbreviation_exclude_selectors', 'string', ''));
        $addon->setConfig('deepl_language_mapping', rex_request::post('deepl_language_mapping', 'string', ''));
        $addon->setConfig('tstr_sprog_syntax', rex_request::post('tstr_sprog_syntax', 'bool'));
        $addon->setConfig('tstr_source_clang_id', rex_request::post('tstr_source_clang_id', 'int', rex_clang::getStartId()));
        $addon->setConfig('tstr_fallback_enabled', rex_request::post('tstr_fallback_enabled', 'bool'));
        $addon->setConfig('tstr_fallback_clang_id', rex_request::post('tstr_fallback_clang_id', 'int', rex_clang::getStartId()));
        $addon->setConfig('tstr_ep_backend', rex_request::post('tstr_ep_backend', 'string', 'SLICE_BE_PREVIEW'));
        $addon->setConfig('tstr_ep_frontend', rex_request::post('tstr_ep_frontend', 'string', 'OUTPUT_FILTER'));

        SnippetsTranslate::clearCache();
        echo rex_view::success(rex_i18n::msg('settings_saved'));
    }
}

// Sprog-Import
if ('import_sprog' === $func) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (!rex_addon::get('sprog')->isAvailable()) {
        echo rex_view::error(rex_i18n::msg('snippets_tstr_import_sprog_not_available'));
    } else {
        $overwrite = rex_request::post('import_overwrite', 'bool', false);

        $sql = rex_sql::factory();
        $sql->setQuery('SELECT wildcard, `replace`, clang_id FROM ' . rex::getTable('sprog_wildcard') . ' ORDER BY wildcard, clang_id');

        $imported = 0;
        $skipped = 0;
        $wildcardMap = [];

        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $wildcard = (string) $sql->getValue('wildcard');
            $replaceValue = (string) $sql->getValue('replace');
            $clangId = (int) $sql->getValue('clang_id');

            if ('' === $wildcard) {
                $sql->next();
                continue;
            }

            if (!isset($wildcardMap[$wildcard])) {
                $wildcardMap[$wildcard] = [];
            }
            $wildcardMap[$wildcard][$clangId] = $replaceValue;
            $sql->next();
        }

        foreach ($wildcardMap as $wildcard => $values) {
            $existing = TranslationStringRepository::getByKey($wildcard);

            if (null !== $existing && !$overwrite) {
                ++$skipped;
                continue;
            }

            if (null !== $existing) {
                TranslationStringRepository::saveValues($existing->getId(), $values);
                ++$imported;
            } else {
                $newId = TranslationStringRepository::save([
                    'key_name' => $wildcard,
                    'status' => 1,
                ]);
                TranslationStringRepository::saveValues($newId, $values);
                ++$imported;
            }
        }

        SnippetsTranslate::clearCache();
        echo rex_view::success(rex_i18n::msg('snippets_tstr_import_sprog_success', (string) $imported, (string) $skipped));
    }
}

// Aktuelle Einstellungen
$phpExecutionEnabled = (bool) $addon->getConfig('php_execution_enabled', true);
$sprogAbbrevEnabled = (bool) $addon->getConfig('sprog_abbrev_enabled', true);
$debugMode = (bool) $addon->getConfig('debug_mode', false);
$htmlReplacementAllowSnippets = (bool) $addon->getConfig('html_replacement_allow_snippets', false);
$abbreviationExcludeSelectors = (string) $addon->getConfig('abbreviation_exclude_selectors', "a\nnav\ncode\npre");
$deeplLanguageMapping = (string) $addon->getConfig('deepl_language_mapping', '');
$tstrSprogSyntax = (bool) $addon->getConfig('tstr_sprog_syntax', false);
$tstrSourceClangId = (int) $addon->getConfig('tstr_source_clang_id', rex_clang::getStartId());
$tstrFallbackEnabled = (bool) $addon->getConfig('tstr_fallback_enabled', false);
$tstrFallbackClangId = (int) $addon->getConfig('tstr_fallback_clang_id', rex_clang::getStartId());
$tstrEpBackend = (string) $addon->getConfig('tstr_ep_backend', 'SLICE_BE_PREVIEW');
$tstrEpFrontend = (string) $addon->getConfig('tstr_ep_frontend', 'OUTPUT_FILTER');

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

        <div class="form-group">
            <label>
                <input type="checkbox" name="html_replacement_allow_snippets" value="1" ' . ($htmlReplacementAllowSnippets ? 'checked' : '') . '>
                ' . $addon->i18n('snippets_settings_html_replacement_allow_snippets') . '
            </label>
            <p class="help-block">' . $addon->i18n('snippets_settings_html_replacement_allow_snippets_help') . '</p>
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
        <legend>' . rex_i18n::msg('snippets_tstr_settings_syntax_title') . '</legend>
        
        <div class="alert alert-info">
            <strong>' . rex_i18n::msg('snippets_tstr_settings_syntax_default') . ':</strong>
            <code>[[ key ]]</code><br>
            <small>' . rex_i18n::msg('snippets_tstr_settings_syntax_default_help') . '</small>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="tstr_sprog_syntax" value="1" ' . ($tstrSprogSyntax ? 'checked' : '') . '>
                ' . rex_i18n::msg('snippets_tstr_settings_sprog_syntax') . '
            </label>
            <p class="help-block">' . rex_i18n::msg('snippets_tstr_settings_sprog_syntax_help') . '</p>
        </div>
    </fieldset>

    <fieldset>
        <legend>' . rex_i18n::msg('snippets_tstr_settings_source_title') . '</legend>
        
        <div class="form-group">
            <label for="tstr_source_clang_id">' . rex_i18n::msg('snippets_tstr_settings_source_label') . '</label>
            <select class="form-control" id="tstr_source_clang_id" name="tstr_source_clang_id">';

foreach (rex_clang::getAll() as $sourceClang) {
    $selected = $sourceClang->getId() === $tstrSourceClangId ? ' selected' : '';
    $content .= '<option value="' . $sourceClang->getId() . '"' . $selected . '>' . rex_escape($sourceClang->getName()) . ' (' . rex_escape($sourceClang->getCode()) . ')</option>';
}

$content .= '
            </select>
            <p class="help-block">' . rex_i18n::msg('snippets_tstr_settings_source_help') . '</p>
        </div>
    </fieldset>

    <fieldset>
        <legend>' . $addon->i18n('snippets_tstr_settings_fallback_title') . '</legend>

        <div class="form-group">
            <label>
                <input type="checkbox" name="tstr_fallback_enabled" value="1" ' . ($tstrFallbackEnabled ? 'checked' : '') . '>
                ' . $addon->i18n('snippets_tstr_settings_fallback_enabled') . '
            </label>
            <p class="help-block">' . $addon->i18n('snippets_tstr_settings_fallback_enabled_help') . '</p>
        </div>

        <div class="form-group">
            <label for="tstr_fallback_clang_id">' . $addon->i18n('snippets_tstr_settings_fallback_label') . '</label>
            <select class="form-control" id="tstr_fallback_clang_id" name="tstr_fallback_clang_id">';

foreach (rex_clang::getAll() as $fbClang) {
    $selected = $fbClang->getId() === $tstrFallbackClangId ? ' selected' : '';
    $content .= '<option value="' . $fbClang->getId() . '"' . $selected . '>' . rex_escape($fbClang->getName()) . ' (' . rex_escape($fbClang->getCode()) . ')</option>';
}

$content .= '
            </select>
            <p class="help-block">' . $addon->i18n('snippets_tstr_settings_fallback_help') . '</p>
        </div>
    </fieldset>

    <fieldset>
        <legend>' . $addon->i18n('snippets_settings_ep_title') . '</legend>
        
        <div class="form-group">
            <label for="tstr_ep_frontend">' . $addon->i18n('snippets_settings_ep_frontend') . '</label>
            <input type="text" class="form-control" id="tstr_ep_frontend" name="tstr_ep_frontend" value="' . rex_escape($tstrEpFrontend) . '">
            <p class="help-block">' . $addon->i18n('snippets_settings_ep_frontend_help') . '</p>
        </div>

        <div class="form-group">
            <label for="tstr_ep_backend">' . $addon->i18n('snippets_settings_ep_backend') . '</label>
            <input type="text" class="form-control" id="tstr_ep_backend" name="tstr_ep_backend" value="' . rex_escape($tstrEpBackend) . '">
            <p class="help-block">' . $addon->i18n('snippets_settings_ep_backend_help') . '</p>
        </div>
    </fieldset>

    <fieldset>
        <legend>' . $addon->i18n('snippets_tstr_settings_title') . '</legend>';

// Aktuelle Sprachen und deren automatisches Mapping anzeigen
$clangInfo = '';
foreach (rex_clang::getAll() as $clang) {
    $autoMapped = \FriendsOfREDAXO\Snippets\Service\SnippetsTranslate::getDeeplLanguageCode($clang->getCode());
    $clangInfo .= '<code>' . rex_escape($clang->getCode()) . '</code> → <code>' . rex_escape($autoMapped) . '</code> (' . rex_escape($clang->getName()) . ')<br>';
}

$content .= '
        <div class="alert alert-info">
            <strong>Aktuelles Mapping:</strong><br>' . $clangInfo . '
        </div>
        <div class="form-group">
            <label for="deepl_language_mapping">' . $addon->i18n('snippets_tstr_settings_mapping') . '</label>
            <textarea class="form-control" id="deepl_language_mapping" name="deepl_language_mapping" rows="5" placeholder="en_gb=EN-GB&#10;pt_br=PT-BR">' . rex_escape($deeplLanguageMapping) . '</textarea>
            <p class="help-block">' . $addon->i18n('snippets_tstr_settings_mapping_help') . '</p>
        </div>
    </fieldset>';

$content .= '
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

// Fehlende Platzhalter Analyse
if ('analyze_placeholders' === $func) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $analysis = SnippetsTranslate::findMissingPlaceholders();

        $analyzeContent = '<div class="row">';
        $analyzeContent .= '<div class="col-sm-4">';
        $analyzeContent .= '<div class="panel panel-default"><div class="panel-body text-center">';
        $analyzeContent .= '<h2 class="text-primary" style="margin:0">' . $analysis['defined_count'] . '</h2>';
        $analyzeContent .= '<small>' . $addon->i18n('snippets_tstr_analyze_defined') . '</small>';
        $analyzeContent .= '</div></div></div>';

        $analyzeContent .= '<div class="col-sm-4">';
        $analyzeContent .= '<div class="panel panel-default"><div class="panel-body text-center">';
        $analyzeContent .= '<h2 class="text-info" style="margin:0">' . $analysis['used_count'] . '</h2>';
        $analyzeContent .= '<small>' . $addon->i18n('snippets_tstr_analyze_used_in_slices') . '</small>';
        $analyzeContent .= '</div></div></div>';

        $analyzeContent .= '<div class="col-sm-4">';
        $analyzeContent .= '<div class="panel panel-default"><div class="panel-body text-center">';
        $analyzeContent .= '<h2 style="margin:0;color:' . ($analysis['missing_count'] > 0 ? '#d9534f' : '#5cb85c') . '">' . $analysis['missing_count'] . '</h2>';
        $analyzeContent .= '<small>' . $addon->i18n('snippets_tstr_analyze_missing') . '</small>';
        $analyzeContent .= '</div></div></div>';
        $analyzeContent .= '</div>';

        if ($analysis['missing_count'] > 0) {
            $analyzeContent .= '<table class="table table-striped table-hover">';
            $analyzeContent .= '<thead><tr>';
            $analyzeContent .= '<th>' . $addon->i18n('snippets_tstr_key') . '</th>';
            $analyzeContent .= '<th>' . $addon->i18n('snippets_tstr_analyze_occurrences') . '</th>';
            $analyzeContent .= '<th>' . $addon->i18n('snippets_tstr_analyze_articles') . '</th>';
            $analyzeContent .= '<th></th>';
            $analyzeContent .= '</tr></thead><tbody>';

            foreach ($analysis['missing'] as $key => $info) {
                $articleLinks = [];
                foreach ($info['articles'] as $articleId) {
                    $article = rex_article::get($articleId);
                    $articleName = null !== $article ? rex_escape($article->getName()) : 'ID ' . $articleId;
                    $articleLinks[] = '<a href="' . rex_url::backendPage('content/edit', ['article_id' => $articleId, 'mode' => 'edit']) . '">' . $articleName . '</a>';
                }

                $analyzeContent .= '<tr>';
                $analyzeContent .= '<td><code>' . rex_escape($key) . '</code></td>';
                $analyzeContent .= '<td><span class="badge">' . $info['count'] . '</span></td>';
                $analyzeContent .= '<td>' . implode(', ', $articleLinks) . '</td>';
                $analyzeContent .= '<td><a href="' . rex_url::backendPage('snippets/translations', ['func' => 'add', 'key' => $key]) . '" class="btn btn-xs btn-success" title="' . $addon->i18n('snippets_tstr_analyze_create') . '"><i class="rex-icon fa-plus"></i></a></td>';
                $analyzeContent .= '</tr>';
            }

            $analyzeContent .= '</tbody></table>';
        } else {
            $analyzeContent .= '<p class="text-success"><i class="rex-icon fa-check"></i> ' . $addon->i18n('snippets_tstr_analyze_all_defined') . '</p>';
        }

        $analyzeFragment = new rex_fragment();
        $analyzeFragment->setVar('title', $addon->i18n('snippets_tstr_analyze_result_title'), false);
        $analyzeFragment->setVar('body', $analyzeContent, false);
        echo $analyzeFragment->parse('core/page/section.php');
    }
}

// Analyse starten Button
$analyzeButtonContent = '
<p>' . $addon->i18n('snippets_tstr_analyze_description') . '</p>
<form method="post" action="' . rex_url::currentBackendPage() . '">
    <input type="hidden" name="func" value="analyze_placeholders">
    ' . $csrfToken->getHiddenField() . '
    <button type="submit" class="btn btn-primary">
        <i class="rex-icon fa-search"></i> ' . $addon->i18n('snippets_tstr_analyze_button') . '
    </button>
</form>';

$analyzeButtonFragment = new rex_fragment();
$analyzeButtonFragment->setVar('title', $addon->i18n('snippets_tstr_analyze_title'), false);
$analyzeButtonFragment->setVar('body', $analyzeButtonContent, false);
echo $analyzeButtonFragment->parse('core/page/section.php');

// Sprog-Import (nur wenn Sprog verfügbar)
if (rex_addon::get('sprog')->isAvailable()) {
    $sprogSql = rex_sql::factory();
    $sprogSql->setQuery('SELECT COUNT(DISTINCT wildcard) as cnt FROM ' . rex::getTable('sprog_wildcard'));
    $sprogCount = (int) $sprogSql->getValue('cnt');

    $importContent = '
    <p>' . rex_i18n::msg('snippets_tstr_import_sprog_text', (string) $sprogCount) . '</p>
    <form method="post" action="' . rex_url::currentBackendPage() . '">
        <input type="hidden" name="func" value="import_sprog">
        ' . $csrfToken->getHiddenField() . '
        <div class="checkbox">
            <label>
                <input type="checkbox" name="import_overwrite" value="1">
                ' . rex_i18n::msg('snippets_tstr_import_sprog_overwrite') . '
            </label>
            <p class="help-block">' . rex_i18n::msg('snippets_tstr_import_sprog_overwrite_help') . '</p>
        </div>
        <button type="submit" class="btn btn-primary" ' . (0 === $sprogCount ? 'disabled' : '') . '>
            <i class="rex-icon fa-download"></i> ' . rex_i18n::msg('snippets_tstr_import_sprog_btn', (string) $sprogCount) . '
        </button>
    </form>';

    $importFragment = new rex_fragment();
    $importFragment->setVar('title', rex_i18n::msg('snippets_tstr_import_sprog_title'), false);
    $importFragment->setVar('body', $importContent, false);
    echo $importFragment->parse('core/page/section.php');
}
