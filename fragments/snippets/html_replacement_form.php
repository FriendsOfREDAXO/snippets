<?php
/**
 * @var int $id
 * @var \FriendsOfREDAXO\Snippets\Domain\HtmlReplacement|null $replacement
 * @var rex_csrf_token $csrf_token
 * @var array<int, string> $templates
 * @var array<int, string> $categories
 * @var array<string, string> $backend_pages
 */

use FriendsOfREDAXO\Snippets\Domain\HtmlReplacement;

$addon = rex_addon::get('snippets');

// Variablen aus Fragment holen
$id = $this->getVar('id', 0);
$replacement = $this->getVar('replacement');
$csrf_token = $this->getVar('csrf_token');
$templates = $this->getVar('templates', []);
$categories = $this->getVar('categories', []);
$backend_pages = $this->getVar('backend_pages', []);

// Werte aus POST oder Objekt
$name = rex_post('name', 'string', $replacement?->getName() ?? '');
$description = rex_post('description', 'string', $replacement?->getDescription() ?? '');
$type = rex_post('type', 'string', $replacement?->getType() ?? HtmlReplacement::TYPE_CSS_SELECTOR);
$searchValue = rex_post('search_value', 'string', $replacement?->getSearchValue() ?? '');
$replacementContent = rex_post('replacement', 'string', $replacement?->getReplacement() ?? '');
$position = rex_post('position', 'string', $replacement?->getPosition() ?? HtmlReplacement::POSITION_REPLACE);
$scopeContext = rex_post('scope_context', 'string', $replacement?->getScopeContext() ?? HtmlReplacement::CONTEXT_FRONTEND);
$scopeTemplates = rex_post('scope_templates', 'array', $replacement?->getScopeTemplates() ?? []);
$scopeBackendPages = rex_post('scope_backend_pages', 'array', $replacement?->getScopeBackendPages() ?? []);
$scopeBackendRequestPattern = rex_post('scope_backend_request_pattern', 'string', $replacement?->getScopeBackendRequestPattern() ?? '');
$scopeCategories = rex_post('scope_categories', 'array', $replacement?->getScopeCategories() ?? []);
$scopeUrlPattern = rex_post('scope_url_pattern', 'string', $replacement?->getScopeUrlPattern() ?? '');
$priority = rex_post('priority', 'int', $replacement?->getPriority() ?? 10);
$status = rex_post('status', 'int', ($replacement?->isActive() ?? true) ? 1 : 0);
?>

<form action="<?= rex_url::currentBackendPage() ?>" method="post" id="html-replacement-form">
    <input type="hidden" name="id" value="<?= $id ?>">
    <?= $csrf_token->getHiddenField() ?>

    <fieldset>
        <legend><?= $addon->i18n('snippets_html_replacement_basics') ?></legend>

        <!-- Name -->
        <div class="form-group">
            <label for="name" class="control-label"><?= $addon->i18n('snippets_html_replacement_name') ?> *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= rex_escape($name) ?>" required autofocus>
        </div>

        <!-- Beschreibung -->
        <div class="form-group">
            <label for="description" class="control-label"><?= $addon->i18n('snippets_html_replacement_description') ?></label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= rex_escape($description) ?></textarea>
        </div>

        <!-- Status -->
        <div class="form-group">
            <label for="status" class="control-label"><?= $addon->i18n('snippets_html_replacement_active') ?></label>
            <select class="form-control" id="status" name="status">
                <option value="1" <?= 1 === (int) $status ? 'selected' : '' ?>><?= $addon->i18n('snippets_status_active') ?></option>
                <option value="0" <?= 0 === (int) $status ? 'selected' : '' ?>><?= $addon->i18n('snippets_status_inactive') ?></option>
            </select>
        </div>

        <!-- Priorität -->
        <div class="form-group">
            <label for="priority" class="control-label"><?= $addon->i18n('snippets_html_replacement_priority') ?></label>
            <input type="number" class="form-control" id="priority" name="priority" value="<?= $priority ?>" min="0" max="100">
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_priority_help') ?></p>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= $addon->i18n('snippets_html_replacement_rule') ?></legend>

        <!-- Typ -->
        <div class="form-group">
            <label for="type" class="control-label"><?= $addon->i18n('snippets_html_replacement_type') ?> *</label>
            <select class="form-control" id="type" name="type" required>
                <?php foreach (HtmlReplacement::getAvailableTypes() as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>>
                        <?= rex_escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Suchwert -->
        <div class="form-group">
            <label for="search_value" class="control-label"><?= $addon->i18n('snippets_html_replacement_search_value') ?> *</label>
            <textarea class="form-control" id="search_value" name="search_value" rows="5" required><?= rex_escape($searchValue) ?></textarea>
            <p class="help-block" id="search-help">
                <span data-type="css_selector"><?= $addon->i18n('snippets_html_replacement_search_help_css') ?></span>
                <span data-type="html_match"><?= $addon->i18n('snippets_html_replacement_search_help_html') ?></span>
                <span data-type="regex"><?= $addon->i18n('snippets_html_replacement_search_help_regex') ?></span>
                <span data-type="php_callback"><?= $addon->i18n('snippets_html_replacement_search_help_callback') ?></span>
            </p>
        </div>

        <!-- Position (nur bei CSS-Selektor) -->
        <div class="form-group" id="position-field">
            <label for="position" class="control-label"><?= $addon->i18n('snippets_html_replacement_position') ?></label>
            <select class="form-control" id="position" name="position">
                <?php foreach (HtmlReplacement::getAvailablePositions() as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $position === $value ? 'selected' : '' ?>>
                        <?= rex_escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Ersetzung -->
        <div class="form-group">
            <label for="replacement" class="control-label"><?= $addon->i18n('snippets_html_replacement_replacement') ?> *</label>
            <textarea class="form-control" id="replacement" name="replacement" rows="10" required><?= rex_escape($replacementContent) ?></textarea>
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_replacement_snippet_hint') ?></p>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= $addon->i18n('snippets_html_replacement_scope') ?></legend>

        <!-- Kontext -->
        <div class="form-group">
            <label for="scope_context" class="control-label"><?= $addon->i18n('snippets_html_replacement_scope_context') ?> *</label>
            <select class="form-control" id="scope_context" name="scope_context" required>
                <?php foreach (HtmlReplacement::getAvailableContexts() as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $scopeContext === $value ? 'selected' : '' ?>>
                        <?= rex_escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Templates (nur Frontend) -->
        <div class="form-group" id="scope-templates-field">
            <label for="scope_templates" class="control-label"><?= $addon->i18n('snippets_html_replacement_scope_templates') ?></label>
            <select class="form-control" id="scope_templates" name="scope_templates[]" multiple size="8">
                <?php foreach ($templates as $templateId => $templateName): ?>
                    <option value="<?= $templateId ?>" <?= in_array($templateId, $scopeTemplates, true) ? 'selected' : '' ?>>
                        <?= rex_escape($templateName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_scope_templates_help') ?></p>
        </div>

        <!-- Kategorien (nur Frontend) -->
        <div class="form-group" id="scope-categories-field">
            <label for="scope_categories" class="control-label"><?= $addon->i18n('snippets_html_replacement_scope_categories') ?></label>
            <select class="form-control" id="scope_categories" name="scope_categories[]" multiple size="8">
                <?php foreach ($categories as $categoryId => $categoryName): ?>
                    <option value="<?= $categoryId ?>" <?= in_array($categoryId, $scopeCategories, true) ? 'selected' : '' ?>>
                        <?= rex_escape($categoryName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_scope_categories_help') ?></p>
        </div>

        <!-- URL-Pattern (nur Frontend) -->
        <div class="form-group" id="scope-url-field">
            <label for="scope_url_pattern" class="control-label"><?= $addon->i18n('snippets_html_replacement_scope_url_pattern') ?></label>
            <input type="text" class="form-control" id="scope_url_pattern" name="scope_url_pattern" value="<?= rex_escape($scopeUrlPattern) ?>">
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_scope_url_help') ?></p>
        </div>

        <!-- Backend-Seiten (nur Backend) -->
        <div class="form-group" id="scope-backend-field">
            <label for="scope_backend_pages" class="control-label"><?= $addon->i18n('snippets_html_replacement_scope_backend_pages') ?></label>
            <select class="form-control selectpicker" id="scope_backend_pages" name="scope_backend_pages[]" multiple size="8" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 3">
                <?php foreach ($backend_pages as $page => $pageName): ?>
                    <option value="<?= $page ?>" <?= in_array($page, $scopeBackendPages, true) ? 'selected' : '' ?>>
                        <?= rex_escape($pageName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_scope_backend_help') ?></p>

            <label for="scope_backend_request_pattern" class="control-label" style="margin-top:10px;"><?= $addon->i18n('snippets_html_replacement_scope_backend_request_pattern') ?></label>
            <textarea class="form-control" id="scope_backend_request_pattern" name="scope_backend_request_pattern" rows="4" placeholder="page=content/edit&function=add&#10;page=mediapool/media&func=delete"><?= rex_escape($scopeBackendRequestPattern) ?></textarea>
            <p class="help-block"><?= $addon->i18n('snippets_html_replacement_scope_backend_request_help') ?></p>
        </div>
    </fieldset>

    <!-- Buttons -->
    <footer class="panel-footer">
        <div class="rex-form-panel-footer">
            <div class="btn-toolbar">
                <button type="submit" name="save" value="1" class="btn btn-save rex-form-aligned">
                    <i class="rex-icon rex-icon-save"></i> <?= $addon->i18n('snippets_save') ?>
                </button>
                <button type="submit" name="save_and_close" value="1" class="btn btn-apply">
                    <i class="rex-icon rex-icon-apply"></i> <?= $addon->i18n('snippets_save_and_close') ?>
                </button>
                <a href="<?= rex_url::backendPage('snippets/html_replacement') ?>" class="btn btn-abort">
                    <i class="rex-icon rex-icon-abort"></i> <?= $addon->i18n('snippets_cancel') ?>
                </a>
            </div>
        </div>
    </footer>
</form>

<script>
jQuery(function($) {
    'use strict';

    // Typ-Änderung
    function updateTypeFields() {
        var type = $('#type').val();
        
        // Position nur bei CSS-Selektor
        if (type === 'css_selector') {
            $('#position-field').show();
        } else {
            $('#position-field').hide();
        }
        
        // Hilfetext anzeigen
        $('#search-help span').hide();
        $('#search-help span[data-type="' + type + '"]').show();
    }
    
    // Kontext-Änderung
    function updateScopeFields() {
        var context = $('#scope_context').val();
        
        // Frontend-spezifische Felder
        if (context === 'frontend' || context === 'both') {
            $('#scope-templates-field').show();
            $('#scope-categories-field').show();
            $('#scope-url-field').show();
        } else {
            $('#scope-templates-field').hide();
            $('#scope-categories-field').hide();
            $('#scope-url-field').hide();
        }
        
        // Backend-spezifische Felder
        if (context === 'backend' || context === 'both') {
            $('#scope-backend-field').show();
        } else {
            $('#scope-backend-field').hide();
        }
    }
    
    // Event-Handler
    $('#type').on('change', updateTypeFields);
    $('#scope_context').on('change', updateScopeFields);
    
    // Initiales Update
    updateTypeFields();
    updateScopeFields();
});
</script>
