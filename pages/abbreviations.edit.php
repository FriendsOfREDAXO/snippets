<?php

/**
 * Snippets AddOn - Abkürzung bearbeiten/anlegen
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Domain\Abbreviation;
use FriendsOfREDAXO\Snippets\Repository\AbbreviationRepository;

$addon = rex_addon::get('snippets');

$id = rex_request('id', 'int', 0);

$abbreviation = null;

// Bearbeiten: Vorhandene Abbreviation laden
if ($id > 0) {
    $abbreviation = AbbreviationRepository::findById($id);
    if (!$abbreviation) {
        echo rex_view::error($addon->i18n('abbreviation_not_found'));
        return;
    }
}

// Neu anlegen
if (!$abbreviation) {
    $abbreviation = Abbreviation::fromArray([
        'id' => 0,
        'abbr' => '',
        'title' => '',
        'description' => '',
        'language' => null,
        'case_sensitive' => false,
        'whole_word' => true,
        'scope_context' => Abbreviation::CONTEXT_FRONTEND,
        'scope_templates' => null,
        'scope_categories' => null,
        'scope_url_pattern' => null,
        'priority' => 50,
        'status' => true,
    ]);
}

// Formular absenden
if (rex_post('save', 'string') === '1' && $abbreviation) {
    $data = [
        'abbr' => rex_post('abbr', 'string', ''),
        'title' => rex_post('title', 'string', ''),
        'description' => rex_post('description', 'string', ''),
        'language' => rex_post('language', 'int', 0),
        'case_sensitive' => rex_post('case_sensitive', 'bool', false),
        'whole_word' => rex_post('whole_word', 'bool', false),
        'scope_context' => rex_post('scope_context', 'string', Abbreviation::CONTEXT_FRONTEND),
        'priority' => rex_post('priority', 'int', 50),
        'status' => rex_post('status', 'int', 1),
    ];
    
    if ($id > 0) {
        $data['id'] = $id;
    }
    
    // Validierung
    $errors = [];
    
    if ('' === trim($data['abbr'])) {
        $errors[] = $addon->i18n('abbreviation_error_abbr_empty');
    }
    
    if ('' === trim($data['title'])) {
        $errors[] = $addon->i18n('abbreviation_error_title_empty');
    }
    
    // Prüfen auf Duplikat
    $existingId = AbbreviationRepository::exists(
        $data['abbr'],
        $data['language'],
        $id
    );
    
    if ($existingId > 0) {
        $errors[] = $addon->i18n('abbreviation_error_duplicate');
    }
    
    if ([] === $errors) {
        $savedId = AbbreviationRepository::save($data);
        if ($savedId > 0) {
            echo rex_view::success($addon->i18n('abbreviation_saved'));
            
            // Redirect zur Übersicht
            header('Location: ' . rex_url::backendPage('snippets/abbreviations/overview'));
            exit;
        } else {
            echo rex_view::error($addon->i18n('abbreviation_save_failed'));
        }
    } else {
        echo rex_view::error(implode('<br>', $errors));
    }
}

// Formular anzeigen
if ($abbreviation) {
    $formElements = [];
    
    // Abkürzung
    $formElements[] = [
        'label' => '<label for="abbr">' . $addon->i18n('abbreviation_abbr') . ' *</label>',
        'field' => '<input type="text" class="form-control" id="abbr" name="abbr" value="' . rex_escape($abbreviation->getAbbr()) . '" required>
                    <p class="help-block">' . $addon->i18n('abbreviation_abbr_info') . '</p>',
    ];
    
    // Titel/Ausschreibung
    $formElements[] = [
        'label' => '<label for="title">' . $addon->i18n('abbreviation_title') . ' *</label>',
        'field' => '<input type="text" class="form-control" id="title" name="title" value="' . rex_escape($abbreviation->getTitle()) . '" required>
                    <p class="help-block">' . $addon->i18n('abbreviation_title_info') . '</p>',
    ];
    
    // Beschreibung (optional)
    $formElements[] = [
        'label' => '<label for="description">' . $addon->i18n('abbreviation_description') . '</label>',
        'field' => '<textarea class="form-control" id="description" name="description" rows="3">' . rex_escape($abbreviation->getDescription()) . '</textarea>
                    <p class="help-block">' . $addon->i18n('abbreviation_description_info') . '</p>',
    ];
    
    // Sprache
    $languageOptions = '<option value="0">' . $addon->i18n('abbreviation_language_all') . '</option>';
    foreach (rex_clang::getAll() as $clang) {
        $selected = $abbreviation->getLanguage() === $clang->getId() ? ' selected' : '';
        $languageOptions .= '<option value="' . $clang->getId() . '"' . $selected . '>' . rex_escape($clang->getName()) . '</option>';
    }
    
    $formElements[] = [
        'label' => '<label for="language">' . $addon->i18n('abbreviation_language') . '</label>',
        'field' => '<select class="form-control" id="language" name="language">' . $languageOptions . '</select>
                    <p class="help-block">' . $addon->i18n('abbreviation_language_info') . '</p>',
    ];
    
    // Optionen
    $caseSensitiveChecked = $abbreviation->isCaseSensitive() ? ' checked' : '';
    $wholeWordChecked = $abbreviation->isWholeWord() ? ' checked' : '';
    
    $formElements[] = [
        'label' => '<label>' . $addon->i18n('abbreviation_options') . '</label>',
        'field' => '
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="case_sensitive" value="1"' . $caseSensitiveChecked . '>
                    ' . $addon->i18n('abbreviation_case_sensitive') . '
                </label>
                <p class="help-block">' . $addon->i18n('abbreviation_case_sensitive_info') . '</p>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="whole_word" value="1"' . $wholeWordChecked . '>
                    ' . $addon->i18n('abbreviation_whole_word') . '
                </label>
                <p class="help-block">' . $addon->i18n('abbreviation_whole_word_info') . '</p>
            </div>
        ',
    ];
    
    // Priorität
    $formElements[] = [
        'label' => '<label for="priority">' . $addon->i18n('abbreviation_priority') . '</label>',
        'field' => '<input type="number" class="form-control" id="priority" name="priority" value="' . (int) $abbreviation->getPriority() . '" min="0" max="100">
                    <p class="help-block">' . $addon->i18n('abbreviation_priority_info') . '</p>',
    ];
    
    // Status
    $statusOptions = '';
    $statusOptions .= '<option value="' . Abbreviation::STATUS_ACTIVE . '"' . ($abbreviation->getStatus() === Abbreviation::STATUS_ACTIVE ? ' selected' : '') . '>' . $addon->i18n('status_active') . '</option>';
    $statusOptions .= '<option value="' . Abbreviation::STATUS_INACTIVE . '"' . ($abbreviation->getStatus() === Abbreviation::STATUS_INACTIVE ? ' selected' : '') . '>' . $addon->i18n('status_inactive') . '</option>';
    
    $formElements[] = [
        'label' => '<label for="status">' . $addon->i18n('abbreviation_status') . '</label>',
        'field' => '<select class="form-control" id="status" name="status">' . $statusOptions . '</select>',
    ];
    
    // Context
    $contextOptions = '';
    $contextOptions .= '<option value="' . Abbreviation::CONTEXT_FRONTEND . '"' . ($abbreviation->getScopeContext() === Abbreviation::CONTEXT_FRONTEND ? ' selected' : '') . '>' . $addon->i18n('context_frontend') . '</option>';
    $contextOptions .= '<option value="' . Abbreviation::CONTEXT_BACKEND . '"' . ($abbreviation->getScopeContext() === Abbreviation::CONTEXT_BACKEND ? ' selected' : '') . '>' . $addon->i18n('context_backend') . '</option>';
    $contextOptions .= '<option value="' . Abbreviation::CONTEXT_BOTH . '"' . ($abbreviation->getScopeContext() === Abbreviation::CONTEXT_BOTH ? ' selected' : '') . '>' . $addon->i18n('context_frontend') . ' & ' . $addon->i18n('context_backend') . '</option>';
    
    $formElements[] = [
        'label' => '<label for="scope_context">' . $addon->i18n('abbreviation_contexts') . '</label>',
        'field' => '<select class="form-control" id="scope_context" name="scope_context">' . $contextOptions . '</select>
                    <p class="help-block">' . $addon->i18n('abbreviation_contexts_info') . '</p>',
    ];
    
    // Formular ausgeben
    $isEdit = $id > 0;
    echo '<section class="rex-page-section">';
    echo '<div class="panel panel-edit">';
    echo '<header class="panel-heading"><div class="panel-title">' . ($isEdit ? $addon->i18n('abbreviation_edit') : $addon->i18n('abbreviation_add')) . '</div></header>';
    echo '<div class="panel-body">';
    echo '<form action="' . rex_url::currentBackendPage(['id' => $id]) . '" method="post">';
    echo '<input type="hidden" name="save" value="1">';
    
    echo '<fieldset>';
    foreach ($formElements as $element) {
        echo '<div class="form-group">';
        echo $element['label'];
        echo $element['field'];
        echo '</div>';
    }
    echo '</fieldset>';
    
    echo '<footer class="panel-footer">';
    echo '<div class="rex-form-panel-footer">';
    echo '<div class="btn-toolbar">';
    echo '<button type="submit" class="btn btn-save rex-form-aligned">';
    echo '<i class="rex-icon rex-icon-save"></i> ' . $addon->i18n('abbreviation_save');
    echo '</button>';
    echo '<a href="' . rex_url::currentBackendPage() . '" class="btn btn-abort">';
    echo '<i class="rex-icon rex-icon-abort"></i> ' . $addon->i18n('abbreviation_abort');
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo '</footer>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</section>';
}
