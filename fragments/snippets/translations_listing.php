<?php

/**
 * Fragment: String-Übersetzungen Listing mit Inline-Bearbeitung
 *
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

use FriendsOfREDAXO\Snippets\Domain\TranslationString;

/** @var array<int, TranslationString> $strings */
$strings = $this->getVar('strings');
/** @var array<int, rex_clang> $clangs */
$clangs = $this->getVar('clangs');
/** @var array<int, array{name: string, icon: string}> $categories */
$categories = $this->getVar('categories', []);
$can_edit = $this->getVar('can_edit');
$is_admin = $this->getVar('is_admin');
$search = $this->getVar('search', '');
$currentCategory = $this->getVar('category', 0);
/** @var rex_csrf_token $csrfToken */
$csrfToken = $this->getVar('csrf_token');
$deeplAvailable = $this->getVar('deepl_available', false);
$sourceClangId = $this->getVar('source_clang_id', rex_clang::getStartId());
$currentPage = $this->getVar('current_page', 1);
$totalPages = $this->getVar('total_pages', 1);
$totalCount = $this->getVar('total_count', 0);
$perPage = $this->getVar('per_page', 50);

$clangCount = count($clangs);

?>

<section class="rex-page-section" id="snippets-translations">

    <!-- Suchleiste & Filter -->
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title"><?= rex_i18n::msg('snippets_tstr_title') ?></div>
        </header>
        <div class="panel-body">
            <form method="get" action="<?= rex_url::currentBackendPage() ?>" data-pjax-container="#rex-js-page-container">
                <input type="hidden" name="page" value="snippets/translations">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?= rex_i18n::msg('snippets_search_placeholder') ?></label>
                            <input type="text" class="form-control" name="search"
                                   value="<?= rex_escape($search) ?>"
                                   placeholder="<?= rex_i18n::msg('snippets_tstr_search_placeholder') ?>">
                        </div>
                    </div>
                    <?php if ([] !== $categories): ?>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?= rex_i18n::msg('snippets_filter_category') ?></label>
                            <select class="form-control selectpicker" name="category" data-live-search="true" data-size="10">
                                <option value="0"><?= rex_i18n::msg('snippets_all') ?></option>
                                <?php foreach ($categories as $catId => $catData): ?>
                                <?php
                                $catName = $catData['name'] ?? '';
                                $catIcon = $catData['icon'] ?? '';
                                $optionContent = '' !== trim($catIcon)
                                    ? '<i class="rex-icon ' . rex_escape(trim($catIcon)) . '"></i> ' . rex_escape($catName)
                                    : rex_escape($catName);
                                ?>
                                <option value="<?= $catId ?>" data-content="<?= rex_escape($optionContent) ?>" <?= (int) $currentCategory === $catId ? 'selected' : '' ?>>
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
    </div>

    <!-- Neuen String hinzufügen -->
    <?php if ($can_edit): ?>
    <div class="panel panel-default">
        <header class="panel-heading">
            <a data-toggle="collapse" href="#snippets-tstr-add-form" class="collapsed panel-title">
                <i class="rex-icon fa-plus"></i> <?= rex_i18n::msg('snippets_tstr_add') ?>
            </a>
        </header>
        <div id="snippets-tstr-add-form" class="panel-collapse collapse">
            <div class="panel-body">
                <form method="post" action="<?= rex_url::currentBackendPage() ?>" data-pjax-container="#rex-js-page-container">
                    <input type="hidden" name="func" value="add">
                    <?= $csrfToken->getHiddenField() ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tstr-key"><?= rex_i18n::msg('snippets_tstr_key') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="tstr-key" name="key_name"
                                       placeholder="z.B. nav.home, footer.copyright"
                                       pattern="[a-zA-Z0-9_\-\.]+" required>
                                <p class="help-block"><?= rex_i18n::msg('snippets_tstr_key_help') ?></p>
                            </div>
                        </div>
                        <?php if ([] !== $categories): ?>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tstr-category"><?= rex_i18n::msg('snippets_form_category') ?></label>
                                <select class="form-control selectpicker" id="tstr-category" name="category_id" data-live-search="true" data-size="10">
                                    <option value="0">–</option>
                                    <?php foreach ($categories as $catId => $catData): ?>
                                    <option value="<?= $catId ?>"><?= rex_escape($catData['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <?php foreach ($clangs as $clang): ?>
                        <div class="col-md-<?= $clangCount <= 4 ? (int)(12 / $clangCount) : 3 ?>">
                            <div class="form-group">
                                <label>
                                    <?= rex_escape($clang->getName()) ?>
                                    <small class="text-muted">(<?= rex_escape($clang->getCode()) ?>)</small>
                                </label>
                                <input type="text" class="form-control" name="value_<?= $clang->getId() ?>"
                                       placeholder="<?= rex_escape($clang->getName()) ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-save">
                        <i class="rex-icon rex-icon-save"></i> <?= rex_i18n::msg('snippets_tstr_add') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Übersetzungsliste -->
    <div class="panel panel-default">
        <?php if ($deeplAvailable && $can_edit && [] !== $strings): ?>
        <!-- Batch-Übersetzung -->
        <div class="panel-body snippets-tstr-batch-bar">
            <form class="form-inline snippets-tstr-batch-form" data-pjax="false">
                <span class="snippets-tstr-batch-label">
                    <i class="rex-icon fa-language"></i> <?= rex_i18n::msg('snippets_tstr_batch_title') ?>
                </span>
                <select class="form-control selectpicker input-sm snippets-tstr-batch-target" id="snippets-batch-target" data-width="auto" data-size="10">
                    <?php foreach ($clangs as $clang): ?>
                    <?php if ($clang->getId() === $sourceClangId) { continue; } ?>
                    <option value="<?= $clang->getId() ?>"
                            data-clang-code="<?= rex_escape($clang->getCode()) ?>">
                        <?= rex_escape($clang->getName()) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="checkbox-inline snippets-tstr-batch-option">
                    <input type="checkbox" class="snippets-tstr-batch-empty-only" checked>
                    <?= rex_i18n::msg('snippets_tstr_batch_empty_only') ?>
                </label>
                <button type="button" class="btn btn-sm btn-default snippets-tstr-batch-translate">
                    <i class="rex-icon fa-language"></i> <?= rex_i18n::msg('snippets_tstr_batch_start') ?>
                </button>
                <span class="snippets-tstr-batch-progress" style="display:none;">
                    <span class="snippets-tstr-batch-progress-bar">
                        <span class="snippets-tstr-batch-progress-fill"></span>
                    </span>
                    <span class="snippets-tstr-batch-progress-text"></span>
                </span>
            </form>
        </div>
        <?php endif; ?>

        <div class="panel-body" style="padding: 0;">
            <?php if ([] === $strings): ?>
            <div class="text-center" style="padding: 30px;">
                <p class="text-muted"><?= rex_i18n::msg('snippets_tstr_empty') ?></p>
            </div>
            <?php else: ?>
            <div class="snippets-tstr-scroll-wrapper">
                <table class="table table-striped table-hover snippets-tstr-table">
                    <thead>
                        <tr>
                            <th class="snippets-tstr-col-key snippets-tstr-col-sticky"><?= rex_i18n::msg('snippets_tstr_key') ?></th>
                            <?php foreach ($clangs as $clang): ?>
                            <th class="snippets-tstr-col-lang">
                                <?= rex_escape($clang->getName()) ?>
                                <small class="text-muted">(<?= rex_escape($clang->getCode()) ?>)</small>
                            </th>
                            <?php endforeach; ?>
                            <?php if ($can_edit): ?>
                            <th class="snippets-tstr-col-actions"><?= rex_i18n::msg('snippets_functions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($strings as $string): ?>
                        <tr class="snippets-tstr-row <?= $string->isActive() ? '' : 'snippets-tstr-inactive' ?>"
                            data-string-id="<?= $string->getId() ?>">
                            <td class="snippets-tstr-col-key snippets-tstr-col-sticky">
                                <div class="snippets-tstr-key-wrapper">
                                    <?php if ($can_edit && [] !== $categories): ?>
                                    <?php
                                        $currentCatId = $string->getCategoryId();
                                        $currentCatIcon = '';
                                        $currentCatName = '';
                                        if (null !== $currentCatId && $currentCatId > 0 && isset($categories[$currentCatId])) {
                                            $currentCatIcon = trim($categories[$currentCatId]['icon'] ?? '');
                                            $currentCatName = $categories[$currentCatId]['name'];
                                        }
                                    ?>
                                    <span class="snippets-tstr-category-trigger"
                                          title="<?= '' !== $currentCatName ? rex_escape($currentCatName) : rex_i18n::msg('snippets_form_category') ?>"
                                          data-string-id="<?= $string->getId() ?>"
                                          data-has-category="<?= (null !== $currentCatId && $currentCatId > 0) ? '1' : '0' ?>">
                                        <i class="rex-icon <?= '' !== $currentCatIcon ? rex_escape($currentCatIcon) : 'fa-folder-o' ?> snippets-tstr-category-icon"></i>
                                    </span>
                                    <?php elseif (null !== $string->getCategoryId() && $string->getCategoryId() > 0 && isset($categories[$string->getCategoryId()])): ?>
                                    <?php
                                        $currentCatId = $string->getCategoryId();
                                        $currentCatIcon = trim($categories[$currentCatId]['icon'] ?? '');
                                        $currentCatName = $categories[$currentCatId]['name'];
                                    ?>
                                    <span class="snippets-tstr-category-trigger" style="cursor:default;"
                                          title="<?= rex_escape($currentCatName) ?>"
                                          data-has-category="1">
                                        <i class="rex-icon <?= '' !== $currentCatIcon ? rex_escape($currentCatIcon) : 'fa-folder-o' ?> snippets-tstr-category-icon"></i>
                                    </span>
                                    <?php endif; ?>
                                    <code class="snippets-tstr-placeholder" title="<?= rex_i18n::msg('snippets_tstr_click_to_edit_key') ?>"
                                          data-key="<?= rex_escape($string->getKey()) ?>"
                                          data-string-id="<?= $string->getId() ?>">&#91;&#91; <?= rex_escape($string->getKey()) ?> &#93;&#93;</code>
                                    <button class="btn btn-xs btn-default snippets-tstr-copy"
                                            data-clipboard-text="[[ <?= rex_escape($string->getKey()) ?> ]]"
                                            title="<?= rex_i18n::msg('snippets_btn_copy_shortcode') ?>">
                                        <i class="rex-icon fa-copy"></i>
                                    </button>
                                </div>
                                <?php if ($can_edit): ?>
                                <div class="snippets-tstr-key-edit" style="display:none;" data-string-id="<?= $string->getId() ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control snippets-tstr-key-input"
                                               value="<?= rex_escape($string->getKey()) ?>"
                                               data-original="<?= rex_escape($string->getKey()) ?>"
                                               pattern="[a-zA-Z0-9_\-\.]+">
                                        <span class="input-group-btn">
                                            <button class="btn btn-save btn-sm snippets-tstr-key-save" type="button" title="<?= rex_i18n::msg('snippets_save') ?>">
                                                <i class="rex-icon fa-check"></i>
                                            </button>
                                            <button class="btn btn-default btn-sm snippets-tstr-key-cancel" type="button" title="<?= rex_i18n::msg('snippets_tstr_cancel') ?>">
                                                <i class="rex-icon fa-times"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($can_edit && [] !== $categories): ?>
                                <div class="snippets-tstr-category-dropdown" style="display:none;" data-string-id="<?= $string->getId() ?>">
                                    <select class="form-control input-sm snippets-tstr-category-select"
                                            data-string-id="<?= $string->getId() ?>"
                                            data-original="<?= $currentCatId ?? 0 ?>">
                                        <option value="0" data-icon="fa-folder-o"<?= null === $currentCatId || 0 === $currentCatId ? ' selected' : '' ?>>– <?= rex_i18n::msg('snippets_form_category') ?> –</option>
                                        <?php foreach ($categories as $catId => $catData): ?>
                                        <?php $catIcon = trim($catData['icon'] ?? ''); ?>
                                        <option value="<?= $catId ?>"
                                                data-icon="<?= '' !== $catIcon ? rex_escape($catIcon) : 'fa-folder' ?>"
                                                <?= $string->getCategoryId() === $catId ? ' selected' : '' ?>>
                                            <?= rex_escape($catData['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <?php if (!$string->isActive()): ?>
                                <span class="label label-default"><?= rex_i18n::msg('snippets_status_inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($clangs as $clang): ?>
                            <td class="snippets-tstr-col-lang">
                                <?php
                                $cellValue = $string->getValue($clang->getId());
                                $escapedValue = rex_escape($cellValue);
                                ?>
                                <?php if ($can_edit): ?>
                                <div class="snippets-tstr-cell"
                                     data-string-id="<?= $string->getId() ?>"
                                     data-clang-id="<?= $clang->getId() ?>"
                                     data-clang-code="<?= rex_escape($clang->getCode()) ?>">
                                    <span class="snippets-tstr-text <?= '' === $cellValue ? 'snippets-tstr-text-empty' : '' ?>"
                                          title="<?= rex_i18n::msg('snippets_tstr_click_to_edit') ?>"><?= '' !== $escapedValue ? $escapedValue : '–' ?></span>
                                    <div class="snippets-tstr-edit" style="display:none;">
                                        <div class="snippets-tstr-input-group">
                                            <input type="text"
                                                   class="form-control input-sm snippets-tstr-value"
                                                   data-string-id="<?= $string->getId() ?>"
                                                   data-clang-id="<?= $clang->getId() ?>"
                                                   data-original="<?= $escapedValue ?>"
                                                   value="<?= $escapedValue ?>"
                                                   placeholder="–">
                                            <?php if ($deeplAvailable): ?>
                                            <button class="btn btn-xs btn-default snippets-tstr-deepl"
                                                    data-string-id="<?= $string->getId() ?>"
                                                    data-clang-id="<?= $clang->getId() ?>"
                                                    data-clang-code="<?= rex_escape($clang->getCode()) ?>"
                                                    title="<?= rex_i18n::msg('snippets_tstr_deepl_translate') ?>"
                                                    type="button">
                                                <i class="rex-icon fa-language"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span><?= '' !== $escapedValue ? $escapedValue : '–' ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <?php if ($can_edit): ?>
                            <td class="snippets-tstr-col-actions">
                                <div class="btn-group btn-group-xs">
                                    <button class="btn btn-save snippets-tstr-save-row"
                                            data-string-id="<?= $string->getId() ?>"
                                            title="<?= rex_i18n::msg('snippets_save') ?>"
                                            disabled>
                                        <i class="rex-icon fa-save"></i>
                                    </button>
                                    <a href="<?= rex_url::currentBackendPage([
                                        'func' => 'toggle_status',
                                        'id' => $string->getId(),
                                    ] + ($search !== '' ? ['search' => $search] : []) + ($currentCategory > 0 ? ['category' => $currentCategory] : [])) ?>"
                                       class="btn btn-default" title="<?= rex_i18n::msg('snippets_toggle_status') ?>">
                                        <?php if ($string->isActive()): ?>
                                        <span class="rex-online"><i class="rex-icon rex-icon-online"></i></span>
                                        <?php else: ?>
                                        <span class="rex-offline"><i class="rex-icon rex-icon-offline"></i></span>
                                        <?php endif; ?>
                                    </a>
                                    <?php if ($is_admin): ?>
                                    <a href="<?= rex_url::currentBackendPage([
                                        'func' => 'delete',
                                        'id' => $string->getId(),
                                    ] + $csrfToken->getUrlParams()) ?>"
                                       class="btn btn-danger"
                                       data-confirm="<?= rex_i18n::msg('snippets_tstr_delete_confirm') ?>"
                                       title="<?= rex_i18n::msg('snippets_delete') ?>">
                                        <i class="rex-icon rex-icon-delete"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="panel-body snippets-tstr-pagination">
                <nav class="text-center">
                    <ul class="pagination" style="margin: 0;">
                        <?php
                        $paginationParams = [];
                        if ('' !== $search) {
                            $paginationParams['search'] = $search;
                        }
                        if ($currentCategory > 0) {
                            $paginationParams['category'] = $currentCategory;
                        }
                        ?>
                        <li<?= 1 === $currentPage ? ' class="disabled"' : '' ?>>
                            <a href="<?= rex_url::currentBackendPage($paginationParams + ['tstr_page' => max(1, $currentPage - 1)]) ?>"
                               data-pjax-container="#rex-js-page-container">&laquo;</a>
                        </li>
                        <?php
                        $startPage = max(1, $currentPage - 3);
                        $endPage = min($totalPages, $currentPage + 3);
                        if ($startPage > 1): ?>
                            <li><a href="<?= rex_url::currentBackendPage($paginationParams + ['tstr_page' => 1]) ?>" data-pjax-container="#rex-js-page-container">1</a></li>
                            <?php if ($startPage > 2): ?><li class="disabled"><span>&hellip;</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($p = $startPage; $p <= $endPage; ++$p): ?>
                        <li<?= $p === $currentPage ? ' class="active"' : '' ?>>
                            <a href="<?= rex_url::currentBackendPage($paginationParams + ['tstr_page' => $p]) ?>"
                               data-pjax-container="#rex-js-page-container"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><li class="disabled"><span>&hellip;</span></li><?php endif; ?>
                            <li><a href="<?= rex_url::currentBackendPage($paginationParams + ['tstr_page' => $totalPages]) ?>" data-pjax-container="#rex-js-page-container"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li<?= $currentPage === $totalPages ? ' class="disabled"' : '' ?>>
                            <a href="<?= rex_url::currentBackendPage($paginationParams + ['tstr_page' => min($totalPages, $currentPage + 1)]) ?>"
                               data-pjax-container="#rex-js-page-container">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

            <div class="panel-footer">
                <small class="text-muted">
                    <?= rex_i18n::msg('snippets_tstr_count', $totalCount) ?>
                    <?php if ($totalPages > 1): ?>
                    · <?= rex_i18n::msg('snippets_tstr_page_info', $currentPage, $totalPages) ?>
                    <?php endif; ?>
                    · <?= rex_i18n::msg('snippets_tstr_lang_count', $clangCount) ?>
                    <?php if ($deeplAvailable): ?>
                    | <i class="rex-icon fa-language"></i> <?= rex_i18n::msg('snippets_tstr_deepl_available') ?>
                    <?php endif; ?>
                </small>
                <?php if ($can_edit): ?>
                <div class="pull-right">
                    <button class="btn btn-sm btn-save snippets-tstr-save-all" disabled>
                        <i class="rex-icon fa-save"></i> <?= rex_i18n::msg('snippets_tstr_save_all') ?>
                    </button>
                </div>
                <div class="clearfix"></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</section>
