<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

use FriendsOfREDAXO\Snippets\Domain\Snippet;
use FriendsOfREDAXO\Snippets\Service\EditorItemsService;
use FriendsOfREDAXO\Snippets\Service\UsageService;

/** @var Snippet|null $snippet */
$snippet = $this->getVar('snippet');
/** @var array<int, array{name: string, icon: string}> $categories */
$categories = $this->getVar('categories');
/** @var rex_csrf_token $csrf_token */
$csrf_token = $this->getVar('csrf_token');
$can_edit_php = $this->getVar('can_edit_php');

$isEdit = $snippet !== null;
$formTitle = $isEdit ? rex_i18n::msg('snippets_edit') : rex_i18n::msg('snippets_add');

// Werte für Formular
$keyName = $snippet ? $snippet->getKey() : '';
$title = $snippet ? $snippet->getTitle() : '';
$description = $snippet ? $snippet->getDescription() : '';
$content = $snippet ? $snippet->getContent() : '';
$contentType = $snippet ? $snippet->getContentType() : 'html';
$context = $snippet ? $snippet->getContext() : 'both';
$status = $snippet ? $snippet->isActive() : true;
$categoryId = $snippet ? $snippet->getCategoryId() : 0;
$isMultilang = $snippet ? $snippet->isMultilang() : false;

$normalizeIconClass = static function (string $icon): string {
    $icon = trim($icon);
    if ('' === $icon) {
        return '';
    }

    if (str_contains($icon, 'fa ')) {
        return trim($icon);
    }

    if (str_starts_with($icon, 'fa-')) {
        return 'fa ' . $icon;
    }

    return 'fa fa-' . ltrim($icon, '-');
};

?>

<section class="rex-page-section">
    <div class="panel panel-edit">
        <header class="panel-heading">
            <div class="panel-title"><?= $formTitle ?></div>
        </header>

        <div class="panel-body">
            <form method="post" action="<?= rex_url::currentBackendPage() ?>">
                <?= $csrf_token->getHiddenField() ?>
                <input type="hidden" name="func" value="save">
                <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $snippet->getId() ?>">
                <?php endif; ?>

                <fieldset>
                    <!-- Key -->
                    <div class="form-group">
                        <label for="snippet-key"><?= rex_i18n::msg('snippets_form_key') ?> <span aria-hidden="true">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="snippet-key"
                               name="key_name"
                               value="<?= rex_escape($keyName) ?>"
                               pattern="[A-Za-z0-9_\-]+"
                               autocomplete="off"
                               spellcheck="false"
                               aria-describedby="snippet-key-help"
                               <?= $isEdit ? 'readonly' : '' ?>
                               required>
                        <p class="help-block" id="snippet-key-help">
                            <?= rex_i18n::msg('snippets_form_key_notice') ?>
                            <?php if ($isEdit): ?>
                                <?= rex_i18n::msg('snippets_form_key_readonly') ?>
                            <?php else: ?>
                                <br><?= rex_i18n::msg('snippets_form_key_preview') ?> <code data-snippets-key-preview data-prefix="[[snippet:" data-suffix="]]">[[snippet:<?= rex_escape('' !== $keyName ? $keyName : '…') ?>]]</code>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Titel -->
                    <div class="form-group">
                        <label for="snippet-title"><?= rex_i18n::msg('snippets_form_title') ?> <span aria-hidden="true">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="snippet-title"
                               name="title"
                               value="<?= rex_escape($title) ?>"
                               aria-describedby="snippet-title-help"
                               required>
                        <p class="help-block" id="snippet-title-help"><?= rex_i18n::msg('snippets_form_title_help') ?></p>
                    </div>

                    <!-- Beschreibung -->
                    <div class="form-group">
                        <label for="snippet-description"><?= rex_i18n::msg('snippets_form_description') ?></label>
                        <textarea class="form-control"
                                  id="snippet-description"
                                  name="description"
                                  aria-describedby="snippet-description-help"
                                  rows="2"><?= rex_escape($description) ?></textarea>
                        <p class="help-block" id="snippet-description-help"><?= rex_i18n::msg('snippets_form_description_help') ?></p>
                    </div>

                    <!-- Content-Type -->
                    <div class="form-group">
                        <label for="snippet-content-type"><?= rex_i18n::msg('snippets_form_content_type') ?></label>
                        <select class="form-control" id="snippet-content-type" name="content_type" aria-describedby="snippet-content-type-help">
                            <option value="html" <?= 'html' === $contentType ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_type_label_html') ?>
                            </option>
                            <option value="text" <?= 'text' === $contentType ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_type_label_text') ?>
                            </option>
                            <?php if ($can_edit_php): ?>
                            <option value="php" <?= 'php' === $contentType ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_type_label_php') ?>
                            </option>
                            <?php endif; ?>
                        </select>
                        <div class="help-block" id="snippet-content-type-help">
                            <ul class="snippets-help-list">
                                <li><strong><?= rex_i18n::msg('snippets_type_label_html') ?>:</strong> <?= rex_i18n::msg('snippets_type_help_html') ?></li>
                                <li><strong><?= rex_i18n::msg('snippets_type_label_text') ?>:</strong> <?= rex_i18n::msg('snippets_type_help_text') ?></li>
                                <?php if ($can_edit_php): ?>
                                <li><strong><?= rex_i18n::msg('snippets_type_label_php') ?>:</strong> <?= rex_i18n::msg('snippets_type_help_php') ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="form-group">
                        <label for="snippet-content"><?= rex_i18n::msg('snippets_form_content') ?></label>
                        <textarea class="form-control rex-code"
                                  id="snippet-content"
                                  name="content"
                                  aria-describedby="snippet-content-help"
                                  rows="15"><?= rex_escape($content) ?></textarea>
                        <p class="help-block" id="snippet-content-help"><?= rex_i18n::msg('snippets_form_content_help') ?></p>
                    </div>

                    <!-- Context -->
                    <div class="form-group">
                        <label for="snippet-context"><?= rex_i18n::msg('snippets_form_context') ?></label>
                        <select class="form-control" id="snippet-context" name="context" aria-describedby="snippet-context-help">
                            <option value="both" <?= 'both' === $context ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_context_both') ?>
                            </option>
                            <option value="frontend" <?= 'frontend' === $context ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_context_frontend') ?>
                            </option>
                            <option value="backend" <?= 'backend' === $context ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_context_backend') ?>
                            </option>
                        </select>
                        <p class="help-block" id="snippet-context-help"><?= rex_i18n::msg('snippets_form_context_help') ?></p>
                    </div>

                    <!-- Kategorie -->
                    <?php if (!empty($categories)): ?>
                    <div class="form-group">
                        <label for="snippet-category"><?= rex_i18n::msg('snippets_form_category') ?></label>
                        <select class="form-control selectpicker" id="snippet-category" name="category_id" data-live-search="true" data-size="10">
                            <option value="0">---</option>
                            <?php foreach ($categories as $catId => $categoryData): ?>
                            <?php
                            $catName = $categoryData['name'] ?? '';
                            $catIcon = $categoryData['icon'] ?? '';
                            $normalizedIconClass = $normalizeIconClass($catIcon);
                            $optionContent = '' !== $normalizedIconClass
                                ? '<i class="rex-icon ' . rex_escape($normalizedIconClass) . '"></i> ' . rex_escape($catName)
                                : rex_escape($catName);
                            ?>
                            <option value="<?= $catId ?>" data-content="<?= rex_escape($optionContent) ?>" <?= $catId === $categoryId ? 'selected' : '' ?>>
                                <?= rex_escape($catName) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Status -->
                    <div class="form-group">
                        <div class="checkbox">
                            <label for="snippet-status">
                                <input type="checkbox"
                                       id="snippet-status"
                                       name="status"
                                       value="1"
                                       aria-describedby="snippet-status-help"
                                       <?= $status ? 'checked' : '' ?>>
                                <?= rex_i18n::msg('snippets_form_status_active') ?>
                            </label>
                        </div>
                        <p class="help-block" id="snippet-status-help"><?= rex_i18n::msg('snippets_form_status_help') ?></p>
                    </div>
                </fieldset>

                <!-- Buttons -->
                <footer class="panel-footer">
                    <div class="rex-form-panel-footer">
                        <div class="btn-toolbar">
                            <button type="submit" class="btn btn-save rex-form-aligned">
                                <i class="rex-icon rex-icon-save"></i> <?= rex_i18n::msg('snippets_btn_save') ?>
                            </button>
                            <button type="submit" name="save_and_close" value="1" class="btn btn-save">
                                <?= rex_i18n::msg('snippets_btn_save_and_close') ?>
                            </button>
                            <a href="<?= rex_url::backendPage('snippets/overview') ?>" class="btn btn-abort">
                                <?= rex_i18n::msg('snippets_btn_cancel') ?>
                            </a>
                            <?php if ($isEdit): ?>
                            <a href="<?= rex_url::currentBackendPage(['func' => 'delete', 'id' => $snippet->getId()] + $csrf_token->getUrlParams()) ?>" 
                               class="btn btn-delete"
                               data-confirm="<?= rex_i18n::msg('snippets_confirm_delete') ?>?">
                                <i class="rex-icon rex-icon-delete"></i> <?= rex_i18n::msg('snippets_btn_delete') ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </footer>
            </form>
        </div>
    </div>
</section>

<?php if ($isEdit): ?>
<?php
$shortcode = '[[snippet:' . $snippet->getKey() . ']]';
$params = EditorItemsService::detectParams($snippet->getContent(), $snippet->getContentType());
$paramExample = '[[snippet:' . $snippet->getKey();
foreach ($params as $param) {
    $paramExample .= '|' . $param['name'] . '=' . ('' !== $param['default'] ? $param['default'] : '…');
}
$paramExample .= ']]';
$usages = UsageService::findSnippetUsage($snippet->getKey());
$isMultiClang = rex_clang::count() > 1;
?>
<!-- Platzhalter & Verwendung -->
<section class="rex-page-section">
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title"><h2 class="snippets-panel-heading"><?= rex_i18n::msg('snippets_usage_panel_title') ?></h2></div>
        </header>
        <div class="panel-body">
            <h3 class="snippets-subheading"><?= rex_i18n::msg('snippets_usage_placeholder_title') ?></h3>
            <p><?= rex_i18n::msg('snippets_usage_placeholder_help') ?></p>
            <p>
                <code><?= rex_escape($shortcode) ?></code>
                <button type="button" class="btn btn-xs btn-default rex-js-copy-shortcode"
                        data-shortcode="<?= rex_escape($shortcode) ?>"
                        aria-label="<?= rex_i18n::msg('snippets_btn_copy_shortcode') . ': ' . rex_escape($shortcode) ?>">
                    <i class="rex-icon fa-copy" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_btn_copy_shortcode') ?>
                </button>
            </p>
            <?php if ([] !== $params): ?>
            <p><?= rex_i18n::msg('snippets_usage_params_help') ?></p>
            <ul>
                <?php foreach ($params as $param): ?>
                <li><code><?= rex_escape($param['name']) ?></code><?php if ('' !== $param['default']): ?> <small class="text-muted snippets-meta">(<?= rex_i18n::msg('snippets_usage_param_default') ?>: <?= rex_escape($param['default']) ?>)</small><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <p>
                <code><?= rex_escape($paramExample) ?></code>
                <button type="button" class="btn btn-xs btn-default rex-js-copy-shortcode"
                        data-shortcode="<?= rex_escape($paramExample) ?>"
                        aria-label="<?= rex_i18n::msg('snippets_btn_copy_shortcode') . ': ' . rex_escape($paramExample) ?>">
                    <i class="rex-icon fa-copy" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_btn_copy_shortcode') ?>
                </button>
            </p>
            <?php endif; ?>

            <h3 class="snippets-subheading"><?= rex_i18n::msg('snippets_usage_where_title') ?></h3>
            <?php if ([] === $usages): ?>
            <p class="text-muted snippets-meta"><?= rex_i18n::msg('snippets_usage_none') ?></p>
            <?php else: ?>
            <p><?= rex_i18n::msg(1 === count($usages) ? 'snippets_usage_count_one' : 'snippets_usage_count', count($usages)) ?>:</p>
            <ul class="snippets-usage-list">
                <?php foreach ($usages as $usage): ?>
                <li>
                    <a href="<?= $usage['url'] ?>"><?= rex_escape($usage['name']) ?></a>
                    <small class="text-muted snippets-meta">
                        <?= rex_i18n::msg('snippets_usage_type_' . $usage['type']) ?><?php if ($isMultiClang && $usage['clang_id'] > 0 && null !== rex_clang::get($usage['clang_id'])): ?> · <?= rex_escape(rex_clang::get($usage['clang_id'])->getName()) ?><?php endif; ?>
                    </small>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$snippet->isActive()): ?>
            <p class="text-warning"><i class="rex-icon fa-exclamation-triangle" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_usage_inactive_warning') ?></p>
            <?php endif; ?>
            <?php endif; ?>
            <p class="help-block"><?= rex_i18n::msg('snippets_usage_scan_notice') ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="sr-only" role="status" aria-live="polite" data-snippets-copy-status data-message="<?= rex_i18n::msg('snippets_copied_to_clipboard') ?>"></div>

<?php if (!$isEdit): ?>
<script nonce="<?= rex_response::getNonce() ?>">
// Platzhalter-Vorschau beim Anlegen eines Snippets
(function () {
    var input = document.getElementById('snippet-key');
    var preview = document.querySelector('[data-snippets-key-preview]');
    if (!input || !preview) {
        return;
    }
    input.addEventListener('input', function () {
        var key = input.value.trim();
        preview.textContent = preview.getAttribute('data-prefix') + (key !== '' ? key : '…') + preview.getAttribute('data-suffix');
    });
})();
</script>
<?php endif; ?>
