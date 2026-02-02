<?php

/**
 * Snippets AddOn - Uninstallation
 *
 * @package redaxo\snippets
 */

rex_sql::factory()->setQuery('DROP TABLE IF EXISTS ' . rex::getTable('snippets_log'));
rex_sql::factory()->setQuery('DROP TABLE IF EXISTS ' . rex::getTable('snippets_translation'));
rex_sql::factory()->setQuery('DROP TABLE IF EXISTS ' . rex::getTable('snippets_snippet'));
rex_sql::factory()->setQuery('DROP TABLE IF EXISTS ' . rex::getTable('snippets_category'));
