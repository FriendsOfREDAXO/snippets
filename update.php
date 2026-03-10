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
    ->ensureColumn(new rex_sql_column('scope_backend_request_pattern', 'text', true))
    ->ensureColumn(new rex_sql_column('scope_categories', 'text', true))
    ->ensureColumn(new rex_sql_column('scope_url_pattern', 'text', true))
    ->ensureColumn(new rex_sql_column('priority', 'int(11)', false, '10'))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
    ->ensureGlobalColumns()
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensureIndex(new rex_sql_index('priority', ['priority']))
    ->ensure();

// Legacy-Daten normalisieren (Status + Backend-Seiten-Scope)
$sql = rex_sql::factory();
$sql->setQuery('SELECT id, status, scope_backend_pages, scope_backend_request_pattern FROM ' . rex::getTable('snippets_html_replacement'));

for ($i = 0; $i < $sql->getRows(); ++$i) {
    $id = (int) $sql->getValue('id');
    $rawStatus = $sql->getValue('status');
    $rawBackendPages = $sql->getValue('scope_backend_pages');
    $rawBackendRequestPattern = $sql->getValue('scope_backend_request_pattern');

    $normalizedStatus = 0;
    if (is_bool($rawStatus)) {
        $normalizedStatus = $rawStatus ? 1 : 0;
    } elseif (is_numeric($rawStatus)) {
        $normalizedStatus = 1 === (int) $rawStatus ? 1 : 0;
    } elseif (is_string($rawStatus)) {
        $statusValue = trim($rawStatus, " \t\n\r\0\x0B|");
        $normalizedStatus = '1' === $statusValue ? 1 : 0;
    }

    $normalizedBackendPages = [];
    if (is_string($rawBackendPages) && '' !== trim($rawBackendPages)) {
        $decodedPages = json_decode($rawBackendPages, true);

        if (is_array($decodedPages)) {
            $pageCandidates = $decodedPages;
        } else {
            $pageCandidates = preg_split('/[\r\n,;|]+/', $rawBackendPages) ?: [];
        }

        foreach ($pageCandidates as $pageCandidate) {
            if (!is_string($pageCandidate)) {
                continue;
            }

            $normalizedPage = trim(strtolower($pageCandidate), " \t\n\r\0\x0B/|");
            if ('' !== $normalizedPage) {
                $normalizedBackendPages[] = $normalizedPage;
            }
        }

        $normalizedBackendPages = array_values(array_unique($normalizedBackendPages));
    }

    $encodedBackendPages = [] === $normalizedBackendPages ? null : json_encode($normalizedBackendPages);
    $normalizedBackendRequestPattern = is_string($rawBackendRequestPattern) ? trim($rawBackendRequestPattern) : null;
    if ('' === $normalizedBackendRequestPattern) {
        $normalizedBackendRequestPattern = null;
    }

    $updateSql = rex_sql::factory();
    $updateSql->setTable(rex::getTable('snippets_html_replacement'));
    $updateSql->setWhere(['id' => $id]);
    $updateSql->setValue('status', $normalizedStatus);
    $updateSql->setValue('scope_backend_pages', $encodedBackendPages);
    $updateSql->setValue('scope_backend_request_pattern', $normalizedBackendRequestPattern);
    $updateSql->update();

    $sql->next();
}

// String-Übersetzungen-Tabellen (ab Version 1.3.0)
rex_sql_table::get(rex::getTable('snippets_string'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('key_name', 'varchar(191)', false))
    ->ensureColumn(new rex_sql_column('category_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
    ->ensureGlobalColumns()
    ->ensureIndex(new rex_sql_index('key_name', ['key_name'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensure();

rex_sql_table::get(rex::getTable('snippets_string_value'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('string_id', 'int(10) unsigned', false))
    ->ensureColumn(new rex_sql_column('clang_id', 'int(10) unsigned', false))
    ->ensureColumn(new rex_sql_column('value', 'text', true))
    ->ensureIndex(new rex_sql_index('unique_string_lang', ['string_id', 'clang_id'], rex_sql_index::UNIQUE))
    ->ensureForeignKey(new rex_sql_foreign_key(
        'fk_snippets_string_value_string',
        rex::getTable('snippets_string'),
        ['string_id' => 'id'],
        rex_sql_foreign_key::CASCADE,
        rex_sql_foreign_key::CASCADE
    ))
    ->ensure();
