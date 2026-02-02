<?php

/**
 * Snippets AddOn - Kategorien-Verwaltung
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\PermissionService;

// Berechtigungsprüfung
if (!PermissionService::isAdmin()) {
    echo rex_view::error(rex_i18n::msg('no_rights'));
    return;
}

$func = rex_request::request('func', 'string');
$id = rex_request::request('id', 'int');
$csrfToken = rex_csrf_token::factory('snippets_category');

// Aktionen
if ('save' === $func) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $name = rex_request::post('name', 'string');
        $icon = rex_request::post('icon', 'string');
        $sortOrder = rex_request::post('sort_order', 'int', 0);

        if ('' === $name) {
            echo rex_view::error(rex_i18n::msg('snippets_category_name') . ': ' . rex_i18n::msg('snippets_required'));
        } else {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('snippets_category'));
            $sql->setValue('name', $name);
            $sql->setValue('icon', $icon);
            $sql->setValue('sort_order', $sortOrder);

            if ($id > 0) {
                $sql->setWhere('id = :id', ['id' => $id]);
                $sql->update();
            } else {
                $sql->insert();
            }

            echo rex_view::success(rex_i18n::msg('snippets_category_saved'));
            $func = '';
            $id = 0;
        }
    }
} elseif ('delete' === $func && $id > 0) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('snippets_category') . ' WHERE id = ?',
            [$id]
        );

        echo rex_view::success(rex_i18n::msg('snippets_category_deleted'));
        $func = '';
        $id = 0;
    }
}

// Kategorie laden für Edit
$category = null;
if ($id > 0 && in_array($func, ['edit', 'save'], true)) {
    $sql = rex_sql::factory();
    $sql->setQuery(
        'SELECT * FROM ' . rex::getTable('snippets_category') . ' WHERE id = ?',
        [$id]
    );

    if ($sql->getRows() > 0) {
        $category = $sql->getRow();
    }
}

// Formular anzeigen
if (in_array($func, ['add', 'edit'], true)) {
    $name = is_array($category) && isset($category['name']) ? (string) $category['name'] : '';
    $icon = is_array($category) && isset($category['icon']) ? (string) $category['icon'] : '';
    $sortOrder = is_array($category) && isset($category['sort_order']) ? (int) $category['sort_order'] : 0;

    $formTitle = 'add' === $func ? rex_i18n::msg('snippets_category_add') : rex_i18n::msg('edit');

    $content = '
    <form method="post" action="' . rex_url::currentBackendPage() . '">
        ' . $csrfToken->getHiddenField() . '
        <input type="hidden" name="func" value="save">
        ' . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '') . '
        
        <fieldset>
            <div class="form-group">
                <label for="category-name">' . rex_i18n::msg('snippets_category_name') . ' *</label>
                <input type="text" class="form-control" id="category-name" name="name" value="' . rex_escape($name) . '" required>
            </div>
            
            <div class="form-group">
                <label for="category-icon">' . rex_i18n::msg('snippets_category_icon') . '</label>
                <input type="text" class="form-control" id="category-icon" name="icon" value="' . rex_escape($icon) . '" placeholder="fa-star">
                <p class="help-block">Font-Awesome Icon-Klasse (z.B. fa-star, fa-folder)</p>
            </div>
            
            <div class="form-group">
                <label for="category-sort">Sortierung</label>
                <input type="number" class="form-control" id="category-sort" name="sort_order" value="' . $sortOrder . '">
            </div>
        </fieldset>
        
        <footer class="panel-footer">
            <div class="rex-form-panel-footer">
                <div class="btn-toolbar">
                    <button type="submit" class="btn btn-save rex-form-aligned">
                        <i class="rex-icon rex-icon-save"></i> ' . rex_i18n::msg('snippets_save') . '
                    </button>
                    <a href="' . rex_url::currentBackendPage() . '" class="btn btn-abort">
                        ' . rex_i18n::msg('cancel') . '
                    </a>
                </div>
            </div>
        </footer>
    </form>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formTitle, false);
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}

// Kategorien-Liste
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM ' . rex::getTable('snippets_category') . ' ORDER BY sort_order, name');

$listContent = '
<table class="table table-striped table-hover">
    <thead>
        <tr>
            <th>ID</th>
            <th>' . rex_i18n::msg('snippets_category_name') . '</th>
            <th>' . rex_i18n::msg('snippets_category_icon') . '</th>
            <th>Sortierung</th>
            <th>' . rex_i18n::msg('snippets_col_functions') . '</th>
        </tr>
    </thead>
    <tbody>';

if (0 === $sql->getRows()) {
    $listContent .= '
        <tr>
            <td colspan="5" class="text-center">' . rex_i18n::msg('no_data_available') . '</td>
        </tr>';
} else {
    for ($i = 0; $i < $sql->getRows(); ++$i) {
        $catId = (int) $sql->getValue('id');
        $catName = rex_escape((string) $sql->getValue('name'));
        $catIcon = (string) $sql->getValue('icon');
        $catSort = (int) $sql->getValue('sort_order');
        $hasIcon = '' !== $catIcon;

        $listContent .= '
        <tr>
            <td>' . $catId . '</td>
            <td>' . $catName . '</td>
            <td>' . ($hasIcon ? '<i class="rex-icon ' . rex_escape($catIcon) . '"></i> ' . rex_escape($catIcon) : '-') . '</td>
            <td>' . $catSort . '</td>
            <td>
                <a href="' . rex_url::currentBackendPage(['func' => 'edit', 'id' => $catId]) . '" class="btn btn-xs btn-default">
                    <i class="rex-icon fa-edit"></i> ' . rex_i18n::msg('edit') . '
                </a>
                <a href="' . rex_url::currentBackendPage(['func' => 'delete', 'id' => $catId] + $csrfToken->getUrlParams()) . '" 
                   class="btn btn-xs btn-delete"
                   data-confirm="' . rex_i18n::msg('snippets_confirm_delete') . '?">
                    <i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('snippets_delete') . '
                </a>
            </td>
        </tr>';

        $sql->next();
    }
}

$listContent .= '
    </tbody>
</table>';

$toolbar = '
<a href="' . rex_url::currentBackendPage(['func' => 'add']) . '" class="btn btn-primary">
    <i class="rex-icon rex-icon-add"></i> ' . rex_i18n::msg('snippets_category_add') . '
</a>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('snippets_categories'), false);
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $listContent, false);
echo $fragment->parse('core/page/section.php');
