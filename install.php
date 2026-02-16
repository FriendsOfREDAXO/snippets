<?php

/**
 * Snippets AddOn - Installation
 *
 * @package redaxo\snippets
 */

// Tabelle für Snippets
rex_sql_table::get(rex::getTable('snippets_snippet'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('key_name', 'varchar(191)', false))
    ->ensureColumn(new rex_sql_column('title', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('content', 'longtext', true))
    ->ensureColumn(new rex_sql_column('content_type', 'varchar(20)', false, 'html'))
    ->ensureColumn(new rex_sql_column('context', 'varchar(20)', false, 'both'))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('category_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('is_multilang', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('html_mode', 'varchar(20)', true))
    ->ensureColumn(new rex_sql_column('html_selector', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('html_position', 'varchar(20)', true))
    ->ensureGlobalColumns()
    ->ensureColumn(new rex_sql_column('revision', 'int(10) unsigned', false, '0'))
    ->ensureIndex(new rex_sql_index('key_name', ['key_name'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensureIndex(new rex_sql_index('context', ['context']))
    ->ensure();

// Tabelle für Snippet-Übersetzungen
rex_sql_table::get(rex::getTable('snippets_translation'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('snippet_id', 'int(10) unsigned', false))
    ->ensureColumn(new rex_sql_column('clang_id', 'int(10) unsigned', false))
    ->ensureColumn(new rex_sql_column('content', 'longtext', true))
    ->ensureIndex(new rex_sql_index('unique_snippet_lang', ['snippet_id', 'clang_id'], rex_sql_index::UNIQUE))
    ->ensureForeignKey(new rex_sql_foreign_key(
        'fk_snippets_translation_snippet',
        rex::getTable('snippets_snippet'),
        ['snippet_id' => 'id'],
        rex_sql_foreign_key::CASCADE,
        rex_sql_foreign_key::CASCADE
    ))
    ->ensure();

// Tabelle für Kategorien
rex_sql_table::get(rex::getTable('snippets_category'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('icon', 'varchar(50)', true))
    ->ensureColumn(new rex_sql_column('sort_order', 'int(11)', false, '0'))
    ->ensure();

// Tabelle für Audit-Log (PHP-Snippets)
rex_sql_table::get(rex::getTable('snippets_log'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('snippet_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('user_login', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('action', 'varchar(50)', false))
    ->ensureColumn(new rex_sql_column('old_content', 'longtext', true))
    ->ensureColumn(new rex_sql_column('new_content', 'longtext', true))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime', false))
    ->ensureIndex(new rex_sql_index('snippet_id', ['snippet_id']))
    ->ensureIndex(new rex_sql_index('created_at', ['created_at']))
    ->ensure();

// Tabelle für HTML-Ersetzungsregeln
rex_sql_table::get(rex::getTable('snippets_html_replacement'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('type', 'varchar(20)', false, 'css_selector'))
    ->ensureColumn(new rex_sql_column('search_value', 'text', false))
    ->ensureColumn(new rex_sql_column('replacement', 'longtext', false))
    ->ensureColumn(new rex_sql_column('position', 'varchar(20)', false, 'replace'))
    ->ensureColumn(new rex_sql_column('scope_context', 'varchar(20)', false, 'frontend'))
    ->ensureColumn(new rex_sql_column('scope_templates', 'text', true))
    ->ensureColumn(new rex_sql_column('scope_backend_pages', 'text', true))
    ->ensureColumn(new rex_sql_column('scope_categories', 'text', true))
    ->ensureColumn(new rex_sql_column('scope_url_pattern', 'text', true))
    ->ensureColumn(new rex_sql_column('priority', 'int(11)', false, '10'))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
    ->ensureGlobalColumns()
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensureIndex(new rex_sql_index('priority', ['priority']))
    ->ensure();

// Beispiel-Snippet erstellen
$sql = rex_sql::factory();
$sql->setQuery('SELECT 1 FROM ' . rex::getTable('snippets_snippet') . ' LIMIT 1');

if (0 === $sql->getRows()) {
    // Beispiel-Kategorie
    $sql->setTable(rex::getTable('snippets_category'));
    $sql->setValues([
        'name' => 'Beispiele',
        'icon' => 'fa-star',
        'sort_order' => 1,
    ]);
    $sql->insert();
    $categoryId = (int) $sql->getLastId();

    // Beispiel-HTML-Snippet mit Repository erstellen
    $snippetData = [
        'key_name' => 'beispiel_html',
        'title' => 'Beispiel HTML',
        'description' => 'Ein einfaches HTML-Snippet als Beispiel',
        'content' => '<div class="alert alert-info">Dies ist ein Beispiel-Snippet!</div>',
        'content_type' => 'html',
        'context' => 'both',
        'status' => 1,
        'category_id' => $categoryId,
        'is_multilang' => 0,
    ];
    
    \FriendsOfREDAXO\Snippets\Repository\SnippetRepository::save($snippetData);
}
