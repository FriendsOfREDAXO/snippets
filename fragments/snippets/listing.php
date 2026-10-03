<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

use FriendsOfREDAXO\Snippets\Domain\Snippet;

/** @var array<int, Snippet> $snippets */
$snippets = $this->getVar('snippets');
/** @var array<int, array{name: string, icon: string}> $categories */
$categories = $this->getVar('categories', []);
$can_edit = $this->getVar('can_edit');
$can_edit_php = $this->getVar('can_edit_php');
$search = (string) $this->getVar('search', '');
$currentCategory = (int) $this->getVar('category', 0);
$currentType = (string) $this->getVar('content_type', '');
/** @var array<string, list<array{type: string, id: int, clang_id: int, name: string, url: string}>> $usages */
$usages = $this->getVar('usages', []);
/** @var array<string, string> $toggleParams */
$toggleParams = $this->getVar('toggle_params', []);
$isMultiClang = rex_clang::count() > 1;

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

$typeLabels = [
    'text' => rex_i18n::msg('snippets_type_label_text'),
    'html' => rex_i18n::msg('snippets_type_label_html'),
    'php' => rex_i18n::msg('snippets_type_label_php'),
];
$typeHelp = [
    'text' => rex_i18n::msg('snippets_type_help_text'),
    'html' => rex_i18n::msg('snippets_type_help_html'),
    'php' => rex_i18n::msg('snippets_type_help_php'),
];
// Hinweis: rex_i18n::msg() liefert bereits HTML-escapten Text

$hasFilter = '' !== $search || $currentCategory > 0 || '' !== $currentType;
$colspan = $can_edit ? 7 : 6;

?>

<section class="rex-page-section">
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title"><?= rex_i18n::msg('snippets_list_title') ?></div>
        </header>

        <div class="panel-body">
            <p class="snippets-intro"><?= rex_i18n::msg('snippets_list_intro') ?></p>

            <!-- Filter -->
            <form method="get" action="<?= rex_url::currentBackendPage() ?>" role="search" aria-label="<?= rex_i18n::msg('snippets_filter_legend') ?>">
                <input type="hidden" name="page" value="snippets/overview">
                <div class="row">
                    <div class="col-sm-6 col-md-4">
                        <div class="form-group">
                            <label for="snippets-filter-search"><?= rex_i18n::msg('snippets_search') ?></label>
                            <input type="search" class="form-control" id="snippets-filter-search" name="search"
                                   value="<?= rex_escape($search) ?>"
                                   placeholder="<?= rex_i18n::msg('snippets_search_placeholder') ?>">
                        </div>
                    </div>
                    <?php if (!empty($categories)): ?>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-group">
                            <label for="snippets-filter-category"><?= rex_i18n::msg('snippets_filter_category') ?></label>
                            <select class="form-control selectpicker" id="snippets-filter-category" name="category" data-live-search="true" data-size="10">
                                <option value="0"><?= rex_i18n::msg('snippets_all') ?></option>
                                <?php foreach ($categories as $catId => $categoryData): ?>
                                <?php
                                $catName = $categoryData['name'] ?? '';
                                $catIcon = $categoryData['icon'] ?? '';
                                $normalizedIconClass = $normalizeIconClass($catIcon);
                                $optionContent = '' !== $normalizedIconClass
                                    ? '<i class="rex-icon ' . rex_escape($normalizedIconClass) . '"></i> ' . rex_escape($catName)
                                    : rex_escape($catName);
                                ?>
                                <option value="<?= $catId ?>" data-content="<?= rex_escape($optionContent) ?>" <?= $currentCategory === $catId ? 'selected' : '' ?>>
                                    <?= rex_escape($catName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-sm-6 col-md-2">
                        <div class="form-group">
                            <label for="snippets-filter-type"><?= rex_i18n::msg('snippets_filter_type') ?></label>
                            <select class="form-control" id="snippets-filter-type" name="content_type">
                                <option value=""><?= rex_i18n::msg('snippets_all_types') ?></option>
                                <?php foreach ($typeLabels as $typeKey => $typeLabel): ?>
                                <option value="<?= $typeKey ?>" <?= $currentType === $typeKey ? 'selected' : '' ?>><?= $typeLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-group">
                            <span class="snippets-filter-spacer" aria-hidden="true">&nbsp;</span>
                            <div class="btn-group snippets-filter-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="rex-icon fa-search" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_search') ?>
                                </button>
                                <?php if ($hasFilter): ?>
                                <a class="btn btn-default" href="<?= rex_url::backendPage('snippets/overview') ?>">
                                    <i class="rex-icon fa-times" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_filter_reset') ?>
                                </a>
                                <?php endif; ?>
                            </div>
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
                <i class="rex-icon rex-icon-add" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_add') ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Tabelle -->
        <div class="table-responsive">
        <table class="table table-striped table-hover snippets-table">
            <caption class="sr-only"><?= rex_i18n::msg('snippets_list_title') ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?= rex_i18n::msg('snippets_col_title') ?></th>
                    <th scope="col"><?= rex_i18n::msg('snippets_col_placeholder') ?></th>
                    <th scope="col"><?= rex_i18n::msg('snippets_col_category') ?></th>
                    <th scope="col"><?= rex_i18n::msg('snippets_col_kind') ?></th>
                    <th scope="col"><?= rex_i18n::msg('snippets_col_usage') ?></th>
                    <th scope="col"><?= rex_i18n::msg('snippets_col_status') ?></th>
                    <?php if ($can_edit): ?>
                    <th scope="col"><span class="sr-only"><?= rex_i18n::msg('snippets_col_functions') ?></span></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($snippets)): ?>
                <tr>
                    <td colspan="<?= $colspan ?>" class="text-center">
                        <?= rex_i18n::msg($hasFilter ? 'snippets_no_snippets_filter' : 'snippets_no_snippets') ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($snippets as $snippet): ?>
                <?php
                $key = $snippet->getKey();
                $shortcode = '[[snippet:' . $key . ']]';
                $contentType = $snippet->getContentType();
                $snippetUsages = $usages[$key] ?? [];
                $canEditThis = $can_edit && ('php' !== $contentType || $can_edit_php);
                $editUrl = rex_url::currentBackendPage(['page' => 'snippets/edit', 'func' => 'edit', 'id' => $snippet->getId()]);
                ?>
                <tr>
                    <td>
                        <?php if ($canEditThis): ?>
                        <a href="<?= $editUrl ?>"><strong><?= rex_escape($snippet->getTitle()) ?></strong></a>
                        <?php else: ?>
                        <strong><?= rex_escape($snippet->getTitle()) ?></strong>
                        <?php endif; ?>
                        <?php if ($snippet->getDescription()): ?>
                        <br><small class="text-muted snippets-meta"><?= rex_escape($snippet->getDescription()) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="snippets-col-placeholder">
                        <code><?= rex_escape($shortcode) ?></code>
                        <button type="button" class="btn btn-xs btn-default rex-js-copy-shortcode"
                                data-shortcode="<?= rex_escape($shortcode) ?>"
                                title="<?= rex_i18n::msg('snippets_btn_copy_shortcode') ?>"
                                aria-label="<?= rex_i18n::msg('snippets_btn_copy_shortcode') . ': ' . rex_escape($shortcode) ?>">
                            <i class="rex-icon fa-copy" aria-hidden="true"></i>
                        </button>
                    </td>
                    <td>
                        <?php
                        $catId = $snippet->getCategoryId();
                        if ($catId && isset($categories[$catId])) {
                            $catName = $categories[$catId]['name'] ?? '';
                            $catIcon = $categories[$catId]['icon'] ?? '';
                            $normalizedIconClass = $normalizeIconClass($catIcon);
                            echo '<span class="label label-default">';
                            if ('' !== $normalizedIconClass) {
                                echo '<i class="rex-icon ' . rex_escape($normalizedIconClass) . '" aria-hidden="true"></i> ';
                            }
                            echo rex_escape($catName) . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="label <?= 'php' === $contentType ? 'label-warning' : 'label-default' ?>" title="<?= $typeHelp[$contentType] ?? '' ?>">
                            <?php if ('php' === $contentType): ?><i class="rex-icon fa-code" aria-hidden="true"></i> <?php endif; ?>
                            <?= $typeLabels[$contentType] ?? rex_escape(strtoupper($contentType)) ?>
                        </span>
                        <?php
                        $contextLabel = match ($snippet->getContext()) {
                            'frontend' => rex_i18n::msg('snippets_context_frontend'),
                            'backend' => rex_i18n::msg('snippets_context_backend'),
                            'both' => rex_i18n::msg('snippets_context_both'),
                            default => rex_escape($snippet->getContext()),
                        };
                        ?>
                        <br><small class="text-muted snippets-meta"><?= rex_i18n::msg('snippets_form_context') ?>: <?= $contextLabel ?></small>
                    </td>
                    <td class="snippets-col-usage">
                        <?php if ([] === $snippetUsages): ?>
                            <span class="text-muted snippets-meta"><?= rex_i18n::msg('snippets_usage_none_short') ?></span>
                        <?php else: ?>
                            <details>
                                <summary><?= rex_i18n::msg(1 === count($snippetUsages) ? 'snippets_usage_count_one' : 'snippets_usage_count', count($snippetUsages)) ?></summary>
                                <ul class="list-unstyled snippets-usage-list">
                                    <?php foreach ($snippetUsages as $usage): ?>
                                    <li>
                                        <a href="<?= $usage['url'] ?>"><?= rex_escape($usage['name']) ?></a>
                                        <small class="text-muted snippets-meta">
                                            <?= rex_i18n::msg('snippets_usage_type_' . $usage['type']) ?><?php if ($isMultiClang && $usage['clang_id'] > 0 && null !== rex_clang::get($usage['clang_id'])): ?> · <?= rex_escape(rex_clang::get($usage['clang_id'])->getName()) ?><?php endif; ?>
                                        </small>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusLabel = $snippet->isActive() ? rex_i18n::msg('snippets_status_active') : rex_i18n::msg('snippets_status_inactive');
                        $statusIcon = $snippet->isActive()
                            ? '<span class="rex-online"><i class="rex-icon rex-icon-online" aria-hidden="true"></i> ' . $statusLabel . '</span>'
                            : '<span class="rex-offline"><i class="rex-icon rex-icon-offline" aria-hidden="true"></i> ' . $statusLabel . '</span>';
                        ?>
                        <?php if ($canEditThis): ?>
                            <a href="<?= rex_url::currentBackendPage(['func' => 'toggle_status', 'id' => $snippet->getId()] + $toggleParams + ('' !== $search ? ['search' => $search] : []) + ($currentCategory ? ['category' => $currentCategory] : []) + ('' !== $currentType ? ['content_type' => $currentType] : [])) ?>"
                               title="<?= rex_i18n::msg($snippet->isActive() ? 'snippets_status_toggle_off' : 'snippets_status_toggle_on') ?>">
                                <?= $statusIcon ?>
                            </a>
                        <?php else: ?>
                            <?= $statusIcon ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($can_edit): ?>
                    <td>
                        <?php if ($canEditThis): ?>
                        <a href="<?= $editUrl ?>" class="btn btn-xs btn-default">
                            <i class="rex-icon fa-edit" aria-hidden="true"></i> <?= rex_i18n::msg('snippets_btn_edit') ?>
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
    </div>
</section>

<div class="sr-only" role="status" aria-live="polite" data-snippets-copy-status data-message="<?= rex_i18n::msg('snippets_copied_to_clipboard') ?>"></div>
