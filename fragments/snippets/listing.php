<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

use FriendsOfREDAXO\Snippets\Domain\Snippet;

/** @var array<int, Snippet> $snippets */
$snippets = $this->getVar('snippets');
/** @var array<int, string> $categories */
$categories = $this->getVar('categories', []);
$can_edit = $this->getVar('can_edit');
$can_edit_php = $this->getVar('can_edit_php');
$search = $this->getVar('search');
$currentCategory = $this->getVar('category');

?>

<section class="rex-page-section">
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title"><?= rex_i18n::msg('snippets_list_title') ?></div>
        </header>

        <!-- Filter -->
        <div class="panel-body">
            <form method="get" action="<?= rex_url::currentBackendPage() ?>">
                <input type="hidden" name="page" value="snippets/overview">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?= rex_i18n::msg('snippets_search_placeholder') ?></label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?= rex_escape($search) ?>" 
                                   placeholder="<?= rex_i18n::msg('snippets_search_placeholder') ?>">
                        </div>
                    </div>
                    <?php if (!empty($categories)): ?>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?= rex_i18n::msg('snippets_filter_category') ?></label>
                            <select class="form-control" name="category">
                                <option value="0"><?= rex_i18n::msg('snippets_all') ?></option>
                                <?php foreach ($categories as $catId => $catName): ?>
                                <option value="<?= $catId ?>" <?= (int) $currentCategory === $catId ? 'selected' : '' ?>>
                                    <?= rex_escape($catName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">
                                <i class="rex-icon fa-search"></i> <?= rex_i18n::msg('snippets_search') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Toolbar -->
        <?php if ($can_edit): ?>
        <div class="panel-body">
            <a href="<?= rex_url::currentBackendPage(['page' => 'snippets/edit', 'func' => 'add']) ?>" 
               class="btn btn-primary">
                <i class="rex-icon rex-icon-add"></i> <?= rex_i18n::msg('snippets_add') ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Tabelle -->
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= rex_i18n::msg('snippets_col_shortcode') ?></th>
                    <th><?= rex_i18n::msg('snippets_col_title') ?></th>
                    <th><?= rex_i18n::msg('snippets_col_category') ?></th>
                    <th><?= rex_i18n::msg('snippets_col_type') ?></th>
                    <th><?= rex_i18n::msg('snippets_col_context') ?></th>
                    <th><?= rex_i18n::msg('snippets_col_status') ?></th>
                    <?php if ($can_edit): ?>
                    <th><?= rex_i18n::msg('snippets_col_functions') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($snippets)): ?>
                <tr>
                    <td colspan="<?= $can_edit ? 7 : 6 ?>" class="text-center">
                        <?= rex_i18n::msg('snippets_no_snippets') ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($snippets as $snippet): ?>
                <tr>
                    <td>
                        <code>[[snippet:<?= rex_escape($snippet->getKey()) ?>]]</code>
                        <button class="btn btn-xs btn-default rex-js-copy-shortcode" 
                                data-shortcode="[[snippet:<?= rex_escape($snippet->getKey()) ?>]]"
                                title="<?= rex_i18n::msg('snippets_btn_copy_shortcode') ?>">
                            <i class="rex-icon fa-copy"></i>
                        </button>
                    </td>
                    <td>
                        <strong><?= rex_escape($snippet->getTitle()) ?></strong>
                        <?php if ($snippet->getDescription()): ?>
                        <br><small class="text-muted"><?= rex_escape($snippet->getDescription()) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $catId = $snippet->getCategoryId();
                        if ($catId && isset($categories[$catId])) {
                            echo '<span class="label label-default">' . rex_escape($categories[$catId]) . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ('php' === $snippet->getContentType()): ?>
                            <span class="label label-warning">
                                <i class="rex-icon fa-code"></i> <?= strtoupper($snippet->getContentType()) ?>
                            </span>
                        <?php else: ?>
                            <span class="label label-default">
                                <?= strtoupper($snippet->getContentType()) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $contextLabel = match($snippet->getContext()) {
                            'frontend' => rex_i18n::msg('snippets_context_frontend'),
                            'backend' => rex_i18n::msg('snippets_context_backend'),
                            'both' => rex_i18n::msg('snippets_context_both'),
                            default => $snippet->getContext(),
                        };
                        ?>
                        <span class="label label-info"><?= $contextLabel ?></span>
                    </td>
                    <td>
                        <?php if ($can_edit): ?>
                            <a href="<?= rex_url::currentBackendPage(['func' => 'toggle_status', 'id' => $snippet->getId()] + ($search ? ['search' => $search] : []) + ($currentCategory ? ['category' => $currentCategory] : [])) ?>">
                                <?php if ($snippet->isActive()): ?>
                                    <span class="rex-online"><i class="rex-icon rex-icon-online"></i></span>
                                <?php else: ?>
                                    <span class="rex-offline"><i class="rex-icon rex-icon-offline"></i></span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <?php if ($snippet->isActive()): ?>
                                <span class="rex-online"><i class="rex-icon rex-icon-online"></i></span>
                            <?php else: ?>
                                <span class="rex-offline"><i class="rex-icon rex-icon-offline"></i></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($can_edit): ?>
                    <td>
                        <?php
                        $canEditThis = $can_edit;
                        if ('php' === $snippet->getContentType() && !$can_edit_php) {
                            $canEditThis = false;
                        }
                        ?>
                        <?php if ($canEditThis): ?>
                        <a href="<?= rex_url::currentBackendPage(['page' => 'snippets/edit', 'func' => 'edit', 'id' => $snippet->getId()]) ?>" 
                           class="btn btn-xs btn-default">
                            <i class="rex-icon fa-edit"></i> <?= rex_i18n::msg('snippets_btn_edit') ?>
                        </a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
