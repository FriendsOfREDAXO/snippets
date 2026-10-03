<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Util\Parser;

/**
 * Ermittelt, wo Snippets verwendet werden („Wo wird das verwendet?“).
 *
 * Durchsucht Artikel-Slices (value1–value20), Templates und Module-Ausgaben nach
 * [[snippet:key …]]-Platzhaltern. Es werden nur Zeilen gelesen, die überhaupt
 * einen Snippet-Platzhalter enthalten (LIKE-Vorfilter in SQL).
 *
 * @package redaxo\snippets
 */
class UsageService
{
    private const NEEDLE = '%[[snippet:%';

    /** @var array<string, list<array{type: string, id: int, clang_id: int, name: string, url: string}>>|null */
    private static ?array $cache = null;

    /**
     * Fundstellen aller Snippets, gruppiert nach Key.
     *
     * Pro Artikel und Sprache wird jede Fundstelle nur einmal gelistet.
     *
     * @return array<string, list<array{type: string, id: int, clang_id: int, name: string, url: string}>>
     */
    public static function findSnippetUsages(): array
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        $usages = [];
        $seen = [];
        $add = static function (string $key, array $usage) use (&$usages, &$seen): void {
            $id = $key . '|' . $usage['type'] . '|' . $usage['id'] . '|' . $usage['clang_id'];
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $usages[$key][] = $usage;
        };

        self::scanSlices($add);
        self::scanTable('template', 'content', $add);
        self::scanTable('module', 'output', $add);

        foreach ($usages as &$list) {
            usort($list, static fn (array $a, array $b): int => [$a['type'], $a['name'], $a['clang_id']] <=> [$b['type'], $b['name'], $b['clang_id']]);
        }
        unset($list);

        return self::$cache = $usages;
    }

    /**
     * @return list<array{type: string, id: int, clang_id: int, name: string, url: string}>
     */
    public static function findSnippetUsage(string $key): array
    {
        return self::findSnippetUsages()[$key] ?? [];
    }

    /**
     * @param callable(string, array{type: string, id: int, clang_id: int, name: string, url: string}): void $add
     */
    private static function scanSlices(callable $add): void
    {
        if (!\rex_plugin::get('structure', 'content')->isAvailable()) {
            return;
        }

        $columns = [];
        for ($i = 1; $i <= 20; ++$i) {
            $columns[] = 's.value' . $i;
        }

        $where = implode(' OR ', array_map(static fn (string $column): string => $column . ' LIKE ?', $columns));

        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT s.article_id, s.clang_id, a.name, ' . implode(', ', $columns)
            . ' FROM ' . \rex::getTable('article_slice') . ' s'
            . ' LEFT JOIN ' . \rex::getTable('article') . ' a ON a.id = s.article_id AND a.clang_id = s.clang_id'
            . ' WHERE ' . $where,
            array_fill(0, count($columns), self::NEEDLE),
        );

        foreach ($sql as $row) {
            $articleId = (int) $row->getValue('article_id');
            $clangId = (int) $row->getValue('clang_id');
            $name = (string) $row->getValue('name');
            for ($i = 1; $i <= 20; ++$i) {
                $value = (string) $row->getValue('value' . $i);
                if (!str_contains($value, '[[snippet:')) {
                    continue;
                }
                foreach (Parser::findAll($value) as $match) {
                    $add($match['key'], [
                        'type' => 'article',
                        'id' => $articleId,
                        'clang_id' => $clangId,
                        'name' => '' !== $name ? $name : '#' . $articleId,
                        'url' => \rex_url::backendPage('content/edit', ['article_id' => $articleId, 'clang' => $clangId, 'mode' => 'edit']),
                    ]);
                }
            }
        }
    }

    /**
     * @param callable(string, array{type: string, id: int, clang_id: int, name: string, url: string}): void $add
     */
    private static function scanTable(string $table, string $column, callable $add): void
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT id, name, ' . $sql->escapeIdentifier($column) . ' AS content FROM ' . \rex::getTable($table)
            . ' WHERE ' . $sql->escapeIdentifier($column) . ' LIKE ?',
            [self::NEEDLE],
        );

        $page = 'template' === $table ? 'templates' : 'modules/modules';
        foreach ($sql as $row) {
            $id = (int) $row->getValue('id');
            foreach (Parser::findAll((string) $row->getValue('content')) as $match) {
                $add($match['key'], [
                    'type' => $table,
                    'id' => $id,
                    'clang_id' => 0,
                    'name' => (string) $row->getValue('name'),
                    'url' => \rex_url::backendPage($page, ['function' => 'edit', $table . '_id' => $id]),
                ]);
            }
        }
    }
}
