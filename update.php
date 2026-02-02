<?php

/**
 * Snippets AddOn - Update
 *
 * @package redaxo\snippets
 */

// HTML-Selector-Felder hinzufügen (ab Version 1.1.0)
rex_sql_table::get(rex::getTable('snippets_snippet'))
    ->ensureColumn(new rex_sql_column('html_mode', 'varchar(20)', true))
    ->ensureColumn(new rex_sql_column('html_selector', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('html_position', 'varchar(20)', true))
    ->alter();

// HTML-Ersetzungs-Tabelle (ab Version 1.2.0)
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
