<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

use FriendsOfREDAXO\Snippets\Domain\Snippet;

/** @var Snippet|null $snippet */
$snippet = $this->getVar('snippet');
/** @var array<int, string> $categories */
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
                        <label for="snippet-key"><?= rex_i18n::msg('snippets_form_key') ?> *</label>
                        <input type="text" 
                               class="form-control" 
                               id="snippet-key" 
                               name="key_name" 
                               value="<?= rex_escape($keyName) ?>"
                               <?= $isEdit ? 'readonly' : '' ?>
                               required>
                        <p class="help-block"><?= rex_i18n::msg('snippets_form_key_notice') ?></p>
                    </div>

                    <!-- Titel -->
                    <div class="form-group">
                        <label for="snippet-title"><?= rex_i18n::msg('snippets_form_title') ?> *</label>
                        <input type="text" 
                               class="form-control" 
                               id="snippet-title" 
                               name="title" 
                               value="<?= rex_escape($title) ?>"
                               required>
                    </div>

                    <!-- Beschreibung -->
                    <div class="form-group">
                        <label for="snippet-description"><?= rex_i18n::msg('snippets_form_description') ?></label>
                        <textarea class="form-control" 
                                  id="snippet-description" 
                                  name="description" 
                                  rows="2"><?= rex_escape($description) ?></textarea>
                    </div>

                    <!-- Content-Type -->
                    <div class="form-group">
                        <label for="snippet-content-type"><?= rex_i18n::msg('snippets_form_content_type') ?></label>
                        <select class="form-control" id="snippet-content-type" name="content_type">
                            <option value="html" <?= 'html' === $contentType ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_type_html') ?>
                            </option>
                            <option value="text" <?= 'text' === $contentType ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_type_text') ?>
                            </option>
                            <?php if ($can_edit_php): ?>
                            <option value="php" <?= 'php' === $contentType ? 'selected' : '' ?>>
                                <?= rex_i18n::msg('snippets_type_php') ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Content -->
                    <div class="form-group">
                        <label for="snippet-content"><?= rex_i18n::msg('snippets_form_content') ?></label>
                        <textarea class="form-control rex-code" 
                                  id="snippet-content" 
                                  name="content" 
                                  rows="15"><?= rex_escape($content) ?></textarea>
                    </div>

                    <!-- Context -->
                    <div class="form-group">
                        <label for="snippet-context"><?= rex_i18n::msg('snippets_form_context') ?></label>
                        <select class="form-control" id="snippet-context" name="context">
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
                    </div>

                    <!-- Kategorie -->
                    <?php if (!empty($categories)): ?>
                    <div class="form-group">
                        <label for="snippet-category"><?= rex_i18n::msg('snippets_form_category') ?></label>
                        <select class="form-control" id="snippet-category" name="category_id">
                            <option value="0">---</option>
                            <?php foreach ($categories as $catId => $catName): ?>
                            <option value="<?= $catId ?>" <?= $catId === $categoryId ? 'selected' : '' ?>>
                                <?= rex_escape($catName) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Status -->
                    <div class="form-group">
                        <label>
                            <input type="checkbox" 
                                   name="status" 
                                   value="1" 
                                   <?= $status ? 'checked' : '' ?>>
                            <?= rex_i18n::msg('snippets_form_status') ?>
                        </label>
                    </div>

                    <!-- Mehrsprachig -->
                    <div class="form-group">
                        <label>
                            <input type="checkbox" 
                                   name="is_multilang" 
                                   value="1" 
                                   <?= $isMultilang ? 'checked' : '' ?>>
                            <?= rex_i18n::msg('snippets_form_multilang') ?>
                        </label>
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
<!-- Shortcode Info -->
<section class="rex-page-section">
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title">Shortcode</div>
        </header>
        <div class="panel-body">
            <code>[[snippet:<?= rex_escape($snippet->getKey()) ?>]]</code>
            <button class="btn btn-xs btn-default rex-js-copy-shortcode" 
                    data-shortcode="[[snippet:<?= rex_escape($snippet->getKey()) ?>]]">
                <i class="rex-icon fa-copy"></i> <?= rex_i18n::msg('snippets_btn_copy_shortcode') ?>
            </button>
        </div>
    </div>
</section>

<script nonce="<?= rex_response::getNonce() ?>">
jQuery(function($) {
    // Copy-to-Clipboard
    $('.rex-js-copy-shortcode').on('click', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode');
        var $btn = $(this);
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode).then(function() {
                $btn.removeClass('btn-default').addClass('btn-success');
                setTimeout(function() {
                    $btn.removeClass('btn-success').addClass('btn-default');
                }, 1500);
            });
        }
    });
});
</script>
<?php endif; ?>
