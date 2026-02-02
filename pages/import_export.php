<?php

/**
 * Snippets AddOn - Import/Export Seite
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

// Export-Aktion
if ('export' === $action && $csrfToken->isValid()) {
    $type = rex_request('export_type', 'string', '');

    $result = match ($type) {
        'snippets' => ImportExportService::exportSnippets(),
        'html_replacements' => ImportExportService::exportHtmlReplacements(),
        'abbreviations' => ImportExportService::exportAbbreviations(),
        default => ['success' => false, 'error' => 'Unknown type'],
    };

    if ($result['success'] && isset($result['data'])) {
        // JSON-Download senden
        $filename = 'snippets_' . $type . '_' . date('Y-m-d_His') . '.json';

        rex_response::cleanOutputBuffers();
        rex_response::sendContentType('application/json');
        rex_response::setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        rex_response::setHeader('Content-Length', (string) strlen($result['data']));
        echo $result['data'];
        exit;
    }

    $error = $result['error'] ?? 'Export failed';
}

// Import-Aktion
if ('import' === $action && $csrfToken->isValid()) {
    $overwrite = rex_request('overwrite', 'bool', false);

    // Datei-Upload prüfen
    $file = rex_files('import_file', ['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE]);

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
            // Validieren
            $validation = ImportExportService::validateJson($json);

            if (!$validation['valid']) {
                $error = $validation['error'] ?? 'Invalid JSON';
            } else {
                // Importieren
                $result = ImportExportService::import($json, $overwrite);

                if ($result['success'] && isset($result['imported'], $result['skipped'])) {
                    $success = sprintf(
                        $addon->i18n('import_success'),
                        $result['imported'],
                        $result['skipped']
                    );
                } else {
                    $error = $result['error'] ?? 'Import failed';
                }
            }
        }
    }
}

// Meldungen anzeigen
if ('' !== $success) {
    echo rex_view::success($success);
}
if ('' !== $error) {
    echo rex_view::error($error);
}

// Export-Bereich
$content = '<h3>' . $addon->i18n('export_title') . '</h3>';
$content .= '<p>' . $addon->i18n('export_description') . '</p>';

$content .= '<form action="' . rex_url::currentBackendPage() . '" method="post" class="form-inline">';
$content .= '<input type="hidden" name="action" value="export">';
$content .= $csrfToken->getHiddenField();

$content .= '<div class="form-group" style="margin-right: 15px;">';
$content .= '<select name="export_type" class="form-control">';
$content .= '<option value="snippets">' . $addon->i18n('export_type_snippets') . '</option>';
$content .= '<option value="html_replacements">' . $addon->i18n('export_type_html_replacements') . '</option>';
$content .= '<option value="abbreviations">' . $addon->i18n('export_type_abbreviations') . '</option>';
$content .= '</select>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary">';
$content .= '<i class="rex-icon fa-download"></i> ' . $addon->i18n('export_button');
$content .= '</button>';

$content .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('export_panel_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Import-Bereich
$content = '<h3>' . $addon->i18n('import_title') . '</h3>';
$content .= '<p>' . $addon->i18n('import_description') . '</p>';

$content .= '<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">';
$content .= '<input type="hidden" name="action" value="import">';
$content .= $csrfToken->getHiddenField();

$content .= '<div class="form-group">';
$content .= '<label for="import_file">' . $addon->i18n('import_file') . '</label>';
$content .= '<input type="file" class="form-control" id="import_file" name="import_file" accept=".json" required>';
$content .= '<p class="help-block">' . $addon->i18n('import_file_help') . '</p>';
$content .= '</div>';

$content .= '<div class="checkbox">';
$content .= '<label>';
$content .= '<input type="checkbox" name="overwrite" value="1"> ';
$content .= $addon->i18n('import_overwrite');
$content .= '</label>';
$content .= '<p class="help-block">' . $addon->i18n('import_overwrite_help') . '</p>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary">';
$content .= '<i class="rex-icon fa-upload"></i> ' . $addon->i18n('import_button');
$content .= '</button>';

$content .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('import_panel_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Info-Box
$content = '<h3>' . $addon->i18n('import_export_info_title') . '</h3>';
$content .= '<p>' . $addon->i18n('import_export_info_text') . '</p>';

$content .= '<h4>' . $addon->i18n('import_export_format_title') . '</h4>';
$content .= '<pre><code>{
  "type": "snippets|html_replacements|abbreviations",
  "version": "1.0",
  "exported_at": "2026-02-02T12:00:00+01:00",
  "count": 5,
  "items": [...]
}</code></pre>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('import_export_info_panel'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('collapse', true, false);
echo $fragment->parse('core/page/section.php');
