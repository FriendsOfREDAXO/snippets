<?php

/**
 * Snippets AddOn - Import/Export Seite
 *
 * Zweistufiger Import mit Vorschau, Konfliktlösung und Sprachmapping.
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\ImportExportService;
use FriendsOfREDAXO\Snippets\Service\PermissionService;

$addon = rex_addon::get('snippets');

// Nur Admins dürfen Import/Export nutzen
if (!PermissionService::isAdmin()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

$csrfToken = rex_csrf_token::factory('snippets_import_export');
$action = rex_request('action', 'string', '');
$success = '';
$error = '';
$analysisResult = null;

// =========================================================================
// Export-Aktion
// =========================================================================
if ('export' === $action && $csrfToken->isValid()) {
    $type = rex_request('export_type', 'string', '');

    $result = match ($type) {
        'snippets' => ImportExportService::exportSnippets(),
        'html_replacements' => ImportExportService::exportHtmlReplacements(),
        'abbreviations' => ImportExportService::exportAbbreviations(),
        'translations' => ImportExportService::exportTranslations(),
        default => ['success' => false, 'error' => 'Unknown type'],
    };

    if ($result['success'] && isset($result['data'])) {
        $filename = 'snippets_' . $type . '_' . date('Y-m-d_His') . '.json';
        $data = $result['data'];

        rex_response::cleanOutputBuffers();

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $data;
        exit;
    }

    $error = $result['error'] ?? 'Export failed';
}

// =========================================================================
// Import Schritt 1: Datei hochladen → Analyse/Vorschau
// =========================================================================
if ('analyze' === $action && $csrfToken->isValid()) {
    $file = rex_files('import_file', 'array');
    if (!is_array($file)) {
        $file = ['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE];
    }

    if (UPLOAD_ERR_OK !== $file['error']) {
        $error = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $addon->i18n('import_error_file_too_large'),
            UPLOAD_ERR_NO_FILE => $addon->i18n('import_error_no_file'),
            default => $addon->i18n('import_error_upload'),
        };
    } else {
        $json = file_get_contents($file['tmp_name']);

        if (false === $json) {
            $error = $addon->i18n('import_error_read');
        } else {
            $analysisResult = ImportExportService::analyzeImport($json);

            if (!$analysisResult['valid']) {
                $error = $analysisResult['error'] ?? 'Invalid file';
                $analysisResult = null;
            } else {
                // JSON in Session cachen für Schritt 2
                rex_set_session('snippets_import_json', $json);
            }
        }
    }
}

// =========================================================================
// Import Schritt 2: Bestätigung mit Optionen → tatsächlicher Import
// =========================================================================
if ('import' === $action && $csrfToken->isValid()) {
    $json = rex_session('snippets_import_json', 'string', '');
    $overwrite = rex_request('overwrite', 'bool', false);

    if ('' === $json) {
        $error = $addon->i18n('import_error_no_data');
    } else {
        $validation = ImportExportService::validateJson($json);

        if (!$validation['valid']) {
            $error = $validation['error'] ?? 'Invalid JSON';
        } else {
            // Sprachmapping aus POST zusammenbauen (nur für Translations)
            $languageMapping = null;
            if ('translations' === ($validation['type'] ?? '')) {
                $languageMapping = [];
                $mappingData = rex_request('lang_mapping', 'array', []);
                $skipLangs = rex_request('skip_lang', 'array', []);

                foreach ($mappingData as $code => $clangId) {
                    $code = (string) $code;
                    $clangId = (int) $clangId;

                    // Sprache überspringen wenn markiert
                    if (isset($skipLangs[$code])) {
                        continue;
                    }

                    if ($clangId > 0) {
                        $languageMapping[$code] = $clangId;
                    }
                }
            }

            $result = ImportExportService::import($json, $overwrite, $languageMapping);

            if ($result['success'] && isset($result['imported'], $result['skipped'])) {
                $msg = sprintf(
                    $addon->i18n('import_success'),
                    $result['imported'],
                    $result['skipped']
                );

                // Zusatzinfo für Translations
                if (isset($result['languages_mapped'], $result['languages_skipped'])) {
                    $msg .= ' ' . sprintf(
                        $addon->i18n('import_translations_lang_info'),
                        $result['languages_mapped'],
                        $result['languages_skipped']
                    );
                }

                $success = $msg;
            } else {
                $error = $result['error'] ?? 'Import failed';
            }
        }

        // Session aufräumen
        rex_unset_session('snippets_import_json');
    }
}

// Meldungen anzeigen
if ('' !== $success) {
    echo rex_view::success($success);
}
if ('' !== $error) {
    echo rex_view::error($error);
}

// =========================================================================
// Analyse-Vorschau anzeigen (Schritt 1 Ergebnis)
// =========================================================================
if (null !== $analysisResult) {
    $type = $analysisResult['type'] ?? '';
    $typeLabel = match ($type) {
        'snippets' => $addon->i18n('export_type_snippets'),
        'html_replacements' => $addon->i18n('export_type_html_replacements'),
        'abbreviations' => $addon->i18n('export_type_abbreviations'),
        'translations' => $addon->i18n('export_type_translations'),
        default => $type,
    };

    $content = '<div class="snippets-import-preview">';

    // Zusammenfassung
    $content .= '<table class="table table-condensed" style="max-width: 500px;">';
    $content .= '<tr><th style="width:200px;">' . $addon->i18n('import_preview_type') . '</th><td><strong>' . rex_escape($typeLabel) . '</strong></td></tr>';
    $content .= '<tr><th>' . $addon->i18n('import_preview_count') . '</th><td>' . (int) ($analysisResult['count'] ?? 0) . '</td></tr>';

    if (isset($analysisResult['new_keys'])) {
        $content .= '<tr><th>' . $addon->i18n('import_preview_new') . '</th><td><span class="label label-success">' . $analysisResult['new_keys'] . ' ' . $addon->i18n('import_preview_new_label') . '</span></td></tr>';
    }
    if (isset($analysisResult['existing_keys'])) {
        $cls = $analysisResult['existing_keys'] > 0 ? 'label-warning' : 'label-default';
        $content .= '<tr><th>' . $addon->i18n('import_preview_existing') . '</th><td><span class="label ' . $cls . '">' . $analysisResult['existing_keys'] . ' ' . $addon->i18n('import_preview_existing_label') . '</span></td></tr>';
    }
    if (isset($analysisResult['exported_at']) && null !== $analysisResult['exported_at']) {
        $content .= '<tr><th>' . $addon->i18n('import_preview_exported_at') . '</th><td>' . rex_escape($analysisResult['exported_at']) . '</td></tr>';
    }
    $content .= '</table>';

    // Sprachmapping für Translations
    if ('translations' === $type && isset($analysisResult['languages']) && [] !== $analysisResult['languages']) {
        $content .= '<h4><i class="rex-icon fa-language"></i> ' . $addon->i18n('import_lang_mapping_title') . '</h4>';
        $content .= '<p class="help-block">' . $addon->i18n('import_lang_mapping_help') . '</p>';

        $content .= '<table class="table table-condensed table-striped" style="max-width: 700px;">';
        $content .= '<thead><tr>';
        $content .= '<th>' . $addon->i18n('import_lang_source') . '</th>';
        $content .= '<th>' . $addon->i18n('import_lang_target') . '</th>';
        $content .= '<th>' . $addon->i18n('import_lang_status') . '</th>';
        $content .= '<th>' . $addon->i18n('import_lang_skip') . '</th>';
        $content .= '</tr></thead><tbody>';

        foreach ($analysisResult['languages'] as $code => $langInfo) {
            $content .= '<tr>';
            $content .= '<td><code>' . rex_escape((string) $code) . '</code></td>';

            // Zielsprache Select
            $content .= '<td>';
            $content .= '<select name="lang_mapping[' . rex_escape((string) $code) . ']" class="form-control input-sm" style="width: auto; display: inline-block;">';

            if ($langInfo['mapped']) {
                $content .= '<option value="' . $langInfo['clang_id'] . '" selected>' . rex_escape($langInfo['clang_name']) . ' (' . rex_escape((string) $code) . ')</option>';
            } else {
                $content .= '<option value="0">– ' . $addon->i18n('import_lang_not_mapped') . ' –</option>';
            }

            // Alle verfügbaren Sprachen als Optionen
            foreach (rex_clang::getAll() as $clang) {
                if ($langInfo['mapped'] && $clang->getId() === $langInfo['clang_id']) {
                    continue;
                }
                $content .= '<option value="' . $clang->getId() . '">' . rex_escape($clang->getName()) . ' (' . rex_escape($clang->getCode()) . ')</option>';
            }

            $content .= '</select>';
            $content .= '</td>';

            // Status
            $content .= '<td>';
            if ($langInfo['mapped']) {
                $content .= '<span class="label label-success"><i class="rex-icon fa-check"></i> ' . $addon->i18n('import_lang_auto_mapped') . '</span>';
            } else {
                $content .= '<span class="label label-danger"><i class="rex-icon fa-exclamation-triangle"></i> ' . $addon->i18n('import_lang_unmapped') . '</span>';
            }
            $content .= '</td>';

            // Skip-Checkbox
            $content .= '<td>';
            $content .= '<label><input type="checkbox" name="skip_lang[' . rex_escape((string) $code) . ']" value="1"';
            if (!$langInfo['mapped']) {
                $content .= ' checked';
            }
            $content .= '> ' . $addon->i18n('import_lang_skip_label') . '</label>';
            $content .= '</td>';

            $content .= '</tr>';
        }

        $content .= '</tbody></table>';

        // Kategorien aus dem Import
        if (isset($analysisResult['categories']) && [] !== $analysisResult['categories']) {
            $content .= '<h4><i class="rex-icon fa-folder"></i> ' . $addon->i18n('import_categories_title') . '</h4>';
            $content .= '<p class="help-block">' . $addon->i18n('import_categories_help') . '</p>';
            $content .= '<ul>';
            foreach ($analysisResult['categories'] as $catName) {
                $content .= '<li>' . rex_escape($catName) . '</li>';
            }
            $content .= '</ul>';
        }
    }

    // Optionen
    $content .= '<hr>';
    $content .= '<div class="checkbox">';
    $content .= '<label>';
    $content .= '<input type="checkbox" name="overwrite" value="1"> ';
    $content .= '<strong>' . $addon->i18n('import_overwrite') . '</strong>';
    $content .= '</label>';
    $content .= '<p class="help-block">' . $addon->i18n('import_overwrite_help') . '</p>';
    $content .= '</div>';

    $content .= '</div>';

    // Form für Schritt 2
    $formContent = '<form action="' . rex_url::currentBackendPage() . '" method="post">';
    $formContent .= '<input type="hidden" name="action" value="import">';
    $formContent .= $csrfToken->getHiddenField();
    $formContent .= $content;
    $formContent .= '<div class="btn-toolbar">';
    $formContent .= '<button type="submit" class="btn btn-primary btn-apply"><i class="rex-icon fa-upload"></i> ' . $addon->i18n('import_confirm_button') . '</button>';
    $formContent .= ' <a href="' . rex_url::currentBackendPage() . '" class="btn btn-default">' . $addon->i18n('import_cancel_button') . '</a>';
    $formContent .= '</div>';
    $formContent .= '</form>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', '<i class="rex-icon fa-search"></i> ' . $addon->i18n('import_preview_panel_title'), false);
    $fragment->setVar('body', $formContent, false);
    echo $fragment->parse('core/page/section.php');

    return; // Nur Vorschau anzeigen
}

// =========================================================================
// Export-Bereich
// =========================================================================
$content = '<p>' . $addon->i18n('export_description') . '</p>';

$content .= '<form action="' . rex_url::currentBackendPage() . '" method="post" class="form-inline">';
$content .= '<input type="hidden" name="action" value="export">';
$content .= $csrfToken->getHiddenField();

$content .= '<div class="form-group" style="margin-right: 15px;">';
$content .= '<select name="export_type" class="form-control selectpicker" data-width="auto">';
$content .= '<option value="snippets">' . $addon->i18n('export_type_snippets') . '</option>';
$content .= '<option value="translations">' . $addon->i18n('export_type_translations') . '</option>';
$content .= '<option value="html_replacements">' . $addon->i18n('export_type_html_replacements') . '</option>';
$content .= '<option value="abbreviations">' . $addon->i18n('export_type_abbreviations') . '</option>';
$content .= '</select>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary">';
$content .= '<i class="rex-icon fa-download"></i> ' . $addon->i18n('export_button');
$content .= '</button>';

$content .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', '<i class="rex-icon fa-download"></i> ' . $addon->i18n('export_panel_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// =========================================================================
// Import-Bereich (Schritt 1: Datei hochladen → Vorschau)
// =========================================================================
$content = '<p>' . $addon->i18n('import_description') . '</p>';

$content .= '<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">';
$content .= '<input type="hidden" name="action" value="analyze">';
$content .= $csrfToken->getHiddenField();

$content .= '<div class="form-group">';
$content .= '<label for="import_file">' . $addon->i18n('import_file') . '</label>';
$content .= '<input type="file" class="form-control" id="import_file" name="import_file" accept=".json" required>';
$content .= '<p class="help-block">' . $addon->i18n('import_file_help') . '</p>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary">';
$content .= '<i class="rex-icon fa-search"></i> ' . $addon->i18n('import_analyze_button');
$content .= '</button>';

$content .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', '<i class="rex-icon fa-upload"></i> ' . $addon->i18n('import_panel_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// =========================================================================
// Info-Box
// =========================================================================
$content = '<p>' . $addon->i18n('import_export_info_text') . '</p>';

$content .= '<h4>' . $addon->i18n('import_export_format_title') . '</h4>';
$content .= '<pre><code>{
  "type": "snippets|html_replacements|abbreviations|translations",
  "version": "1.0",
  "exported_at": "2026-03-10T12:00:00+01:00",
  "count": 5,
  "items": [...]
}</code></pre>';

$content .= '<h4>' . $addon->i18n('import_export_translations_format_title') . '</h4>';
$content .= '<p>' . $addon->i18n('import_export_translations_format_text') . '</p>';

$content .= '<pre><code>{
  "type": "translations",
  "version": "1.0",
  "languages": ["de", "en", "fr"],
  "count": 2,
  "items": [
    {
      "key": "nav.home",
      "status": 1,
      "category": "Navigation",
      "translations": {
        "de": "Startseite",
        "en": "Home",
        "fr": "Accueil"
      }
    }
  ]
}</code></pre>';

$fragment = new rex_fragment();
$fragment->setVar('title', '<i class="rex-icon fa-info-circle"></i> ' . $addon->i18n('import_export_info_panel'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('collapse', true, false);
echo $fragment->parse('core/page/section.php');
