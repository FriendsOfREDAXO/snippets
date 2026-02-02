<?php

namespace FriendsOfREDAXO\Snippets\Repository;

use FriendsOfREDAXO\Snippets\Domain\Snippet;

/**
 * Repository für Snippets
 *
 * @package redaxo\snippets
 */
class SnippetRepository
{
    /**
     * Findet ein Snippet anhand des Keys
     */
    public static function getByKey(string $key): ?Snippet
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('snippets_snippet') . ' WHERE key_name = ?',
            [$key]
        );

        if (0 === $sql->getRows()) {
            return null;
        }

        $data = [];
        foreach ($sql->getFieldnames() as $fieldname) {
            $data[$fieldname] = $sql->getValue($fieldname);
        }

        return Snippet::fromArray($data);
    }

    /**
     * Findet ein Snippet anhand der ID
     */
    public static function getById(int $id): ?Snippet
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('snippets_snippet') . ' WHERE id = ?',
            [$id]
        );

        if (0 === $sql->getRows()) {
            return null;
        }

        $data = [];
        foreach ($sql->getFieldnames() as $fieldname) {
            $data[$fieldname] = $sql->getValue($fieldname);
        }

        return Snippet::fromArray($data);
    }

    /**
     * Findet alle Snippets
     *
     * @param array<string, mixed> $filters
     * @return array<int, Snippet>
     */
    public static function findAll(array $filters = []): array
    {
        $sql = \rex_sql::factory();
        $where = [];
        $params = [];

        // Filter: Suche
        if (isset($filters['search']) && '' !== $filters['search']) {
            $where[] = '(title LIKE ? OR key_name LIKE ? OR description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter: Kategorie
        if (isset($filters['category']) && $filters['category'] > 0) {
            $where[] = 'category_id = ?';
            $params[] = (int) $filters['category'];
        }

        // Filter: Context
        if (isset($filters['context']) && '' !== $filters['context']) {
            $where[] = '(context = ? OR context = ?)';
            $params[] = $filters['context'];
            $params[] = 'both';
        }

        // Filter: Content-Type
        if (isset($filters['content_type']) && '' !== $filters['content_type']) {
            $where[] = 'content_type = ?';
            $params[] = $filters['content_type'];
        }

        // Filter: Status
        if (isset($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = (int) $filters['status'];
        }

        $query = 'SELECT * FROM ' . \rex::getTable('snippets_snippet');

        if (count($where) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY title ASC';

        $sql->setQuery($query, $params);

        $snippets = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $data = [];
            foreach ($sql->getFieldnames() as $fieldname) {
                $data[$fieldname] = $sql->getValue($fieldname);
            }
            $snippets[] = Snippet::fromArray($data);
            $sql->next();
        }

        return $snippets;
    }

    /**
     * Holt Übersetzung für ein Snippet
     */
    public static function getTranslation(int $snippetId, int $clangId): ?string
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT content FROM ' . \rex::getTable('snippets_translation') . ' WHERE snippet_id = ? AND clang_id = ?',
            [$snippetId, $clangId]
        );

        if (0 === $sql->getRows()) {
            return null;
        }

        return (string) $sql->getValue('content');
    }

    /**
     * Speichert ein Snippet
     *
     * @param array<string, mixed> $data
     */
    public static function save(array $data): int
    {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('snippets_snippet'));

        $user = \rex::getUser();
        $userLogin = null !== $user ? $user->getLogin() : 'system';
        $now = date('Y-m-d H:i:s');

        if (isset($data['id']) && $data['id'] > 0) {
            // Update - lade vorher die aktuellen Daten
            $sql->setWhere('id = :id', ['id' => $data['id']]);
            $sql->select();
            
            if (0 === $sql->getRows()) {
                throw new \rex_exception('Snippet mit ID ' . $data['id'] . ' nicht gefunden');
            }
            
            $currentRevision = (int) $sql->getValue('revision');
            
            $sql->setTable(\rex::getTable('snippets_snippet'));
            $sql->setWhere('id = :id', ['id' => $data['id']]);
            $sql->setValue('updatedate', $now);
            $sql->setValue('updateuser', $userLogin);
            $sql->setValue('revision', $currentRevision + 1);
        } else {
            // Insert
            $sql->setValue('createdate', $now);
            $sql->setValue('createuser', $userLogin);
            $sql->setValue('updatedate', $now);
            $sql->setValue('updateuser', $userLogin);
            $sql->setValue('revision', 0);
        }

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'createdate', 'createuser', 'updatedate', 'updateuser', 'revision'], true)) {
                continue;
            }
            $sql->setValue($key, $value);
        }

        if (isset($data['id']) && $data['id'] > 0) {
            $sql->update();
            return (int) $data['id'];
        }

        $sql->insert();
        return (int) $sql->getLastId();
    }

    /**
     * Löscht ein Snippet
     */
    public static function delete(int $id): void
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . \rex::getTable('snippets_snippet') . ' WHERE id = ?',
            [$id]
        );
    }

    /**
     * Findet mehrere Snippets anhand ihrer Keys in einer Query (Performance-Optimierung)
     *
     * @param array<string> $keys
     * @return array<string, Snippet> Key => Snippet Mapping
     */
    public static function findByKeys(array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        // Duplikate entfernen
        $keys = array_unique($keys);

        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('snippets_snippet') . ' WHERE key_name IN (' . $placeholders . ')',
            array_values($keys)
        );

        $snippets = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $data = [];
            foreach ($sql->getFieldnames() as $fieldname) {
                $data[$fieldname] = $sql->getValue($fieldname);
            }
            $snippet = Snippet::fromArray($data);
            $snippets[$snippet->getKey()] = $snippet;
            $sql->next();
        }

        return $snippets;
    }

    /**
     * Holt mehrere Übersetzungen auf einmal (Performance-Optimierung)
     *
     * @param array<int> $snippetIds
     * @return array<int, string> snippet_id => content Mapping
     */
    public static function findTranslationsByIds(array $snippetIds, int $clangId): array
    {
        if ([] === $snippetIds) {
            return [];
        }

        $snippetIds = array_unique($snippetIds);

        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($snippetIds), '?'));
        $params = array_merge(array_values($snippetIds), [$clangId]);

        $sql->setQuery(
            'SELECT snippet_id, content FROM ' . \rex::getTable('snippets_translation') .
            ' WHERE snippet_id IN (' . $placeholders . ') AND clang_id = ?',
            $params
        );

        $translations = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $translations[(int) $sql->getValue('snippet_id')] = (string) $sql->getValue('content');
            $sql->next();
        }

        return $translations;
    }
}
