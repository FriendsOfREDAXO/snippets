<?php

namespace FriendsOfREDAXO\Snippets\Repository;

use FriendsOfREDAXO\Snippets\Domain\TranslationString;

/**
 * Repository für String-Übersetzungen (Sprog-Ersatz)
 *
 * @package redaxo\snippets
 */
class TranslationStringRepository
{
    /**
     * Findet einen String anhand der ID
     */
    public static function getById(int $id): ?TranslationString
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('snippets_string') . ' WHERE id = ?',
            [$id]
        );

        if (0 === $sql->getRows()) {
            return null;
        }

        $data = [];
        foreach ($sql->getFieldnames() as $fieldname) {
            $data[$fieldname] = $sql->getValue($fieldname);
        }

        $entity = TranslationString::fromArray($data);
        $entity->setValues(self::loadValues($entity->getId()));

        return $entity;
    }

    /**
     * Findet einen String anhand des Keys
     */
    public static function getByKey(string $key): ?TranslationString
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('snippets_string') . ' WHERE key_name = ?',
            [$key]
        );

        if (0 === $sql->getRows()) {
            return null;
        }

        $data = [];
        foreach ($sql->getFieldnames() as $fieldname) {
            $data[$fieldname] = $sql->getValue($fieldname);
        }

        $entity = TranslationString::fromArray($data);
        $entity->setValues(self::loadValues($entity->getId()));

        return $entity;
    }

    /**
     * Findet alle Strings mit optionaler Paginierung
     *
     * @param array<string, mixed> $filters
     * @return array<int, TranslationString>
     */
    public static function findAll(array $filters = []): array
    {
        $sql = \rex_sql::factory();
        $where = [];
        $params = [];

        if (isset($filters['search']) && '' !== $filters['search']) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = '(s.key_name LIKE ? OR sv.value LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (isset($filters['category']) && $filters['category'] > 0) {
            $where[] = 's.category_id = ?';
            $params[] = (int) $filters['category'];
        }

        if (isset($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = (int) $filters['status'];
        }

        $query = 'SELECT DISTINCT s.* FROM ' . \rex::getTable('snippets_string') . ' s';

        // JOIN für Suche in Werten
        if (isset($filters['search']) && '' !== $filters['search']) {
            $query .= ' LEFT JOIN ' . \rex::getTable('snippets_string_value') . ' sv ON s.id = sv.string_id';
        }

        if ([] !== $where) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY s.key_name ASC';

        // Paginierung
        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
            $query .= ' LIMIT ' . (int) $filters['limit'] . ' OFFSET ' . $offset;
        }

        $sql->setQuery($query, $params);

        $strings = [];
        $ids = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $data = [];
            foreach ($sql->getFieldnames() as $fieldname) {
                $data[$fieldname] = $sql->getValue($fieldname);
            }
            $entity = TranslationString::fromArray($data);
            $strings[$entity->getId()] = $entity;
            $ids[] = $entity->getId();
            $sql->next();
        }

        // Alle Werte in einer Query laden
        if ([] !== $ids) {
            $allValues = self::loadValuesBatch($ids);
            foreach ($allValues as $stringId => $values) {
                if (isset($strings[$stringId])) {
                    $strings[$stringId]->setValues($values);
                }
            }
        }

        return array_values($strings);
    }

    /**
     * Zählt alle Strings (für Paginierung)
     *
     * @param array<string, mixed> $filters
     */
    public static function count(array $filters = []): int
    {
        $sql = \rex_sql::factory();
        $where = [];
        $params = [];

        if (isset($filters['search']) && '' !== $filters['search']) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = '(s.key_name LIKE ? OR sv.value LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (isset($filters['category']) && $filters['category'] > 0) {
            $where[] = 's.category_id = ?';
            $params[] = (int) $filters['category'];
        }

        if (isset($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = (int) $filters['status'];
        }

        $query = 'SELECT COUNT(DISTINCT s.id) as cnt FROM ' . \rex::getTable('snippets_string') . ' s';

        if (isset($filters['search']) && '' !== $filters['search']) {
            $query .= ' LEFT JOIN ' . \rex::getTable('snippets_string_value') . ' sv ON s.id = sv.string_id';
        }

        if ([] !== $where) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql->setQuery($query, $params);

        return (int) $sql->getValue('cnt');
    }

    /**
     * Speichert einen String (nur Metadaten)
     *
     * @param array<string, mixed> $data
     */
    public static function save(array $data): int
    {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('snippets_string'));

        $user = \rex::getUser();
        $userLogin = null !== $user ? $user->getLogin() : 'system';
        $now = date('Y-m-d H:i:s');

        if (isset($data['id']) && $data['id'] > 0) {
            $sql->setWhere('id = :id', ['id' => $data['id']]);
            $sql->setValue('updatedate', $now);
            $sql->setValue('updateuser', $userLogin);
        } else {
            $sql->setValue('createdate', $now);
            $sql->setValue('createuser', $userLogin);
            $sql->setValue('updatedate', $now);
            $sql->setValue('updateuser', $userLogin);
        }

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'createdate', 'createuser', 'updatedate', 'updateuser'], true)) {
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
     * Speichert den Wert für eine Sprache
     */
    public static function saveValue(int $stringId, int $clangId, string $value): void
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . \rex::getTable('snippets_string_value') .
            ' WHERE string_id = ? AND clang_id = ?',
            [$stringId, $clangId]
        );

        $sql2 = \rex_sql::factory();
        $sql2->setTable(\rex::getTable('snippets_string_value'));

        if ($sql->getRows() > 0) {
            $sql2->setWhere('string_id = :string_id AND clang_id = :clang_id', [
                'string_id' => $stringId,
                'clang_id' => $clangId,
            ]);
            $sql2->setValue('value', $value);
            $sql2->update();
        } else {
            $sql2->setValue('string_id', $stringId);
            $sql2->setValue('clang_id', $clangId);
            $sql2->setValue('value', $value);
            $sql2->insert();
        }
    }

    /**
     * Speichert alle Werte für einen String
     *
     * @param array<int, string> $values clang_id => value
     */
    public static function saveValues(int $stringId, array $values): void
    {
        foreach ($values as $clangId => $value) {
            self::saveValue($stringId, $clangId, $value);
        }
    }

    /**
     * Löscht einen String inkl. aller Werte (FK CASCADE)
     */
    public static function delete(int $id): void
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . \rex::getTable('snippets_string') . ' WHERE id = ?',
            [$id]
        );
    }

    /**
     * Lädt Werte für einen String
     *
     * @return array<int, string> clang_id => value
     */
    public static function loadValues(int $stringId): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT clang_id, value FROM ' . \rex::getTable('snippets_string_value') . ' WHERE string_id = ?',
            [$stringId]
        );

        $values = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $values[(int) $sql->getValue('clang_id')] = (string) $sql->getValue('value');
            $sql->next();
        }

        return $values;
    }

    /**
     * Lädt Werte für mehrere Strings in einer Query (Performance)
     *
     * @param array<int> $stringIds
     * @return array<int, array<int, string>> string_id => [clang_id => value]
     */
    public static function loadValuesBatch(array $stringIds): array
    {
        if ([] === $stringIds) {
            return [];
        }

        $sql = \rex_sql::factory();
        $placeholders = implode(',', array_fill(0, count($stringIds), '?'));
        $sql->setQuery(
            'SELECT string_id, clang_id, value FROM ' . \rex::getTable('snippets_string_value') .
            ' WHERE string_id IN (' . $placeholders . ')',
            array_values($stringIds)
        );

        $values = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $stringId = (int) $sql->getValue('string_id');
            $clangId = (int) $sql->getValue('clang_id');
            $values[$stringId][$clangId] = (string) $sql->getValue('value');
            $sql->next();
        }

        return $values;
    }

    /**
     * Findet alle aktiven Strings und ihre Werte für eine Sprache (Frontend-Replacement)
     *
     * @return array<string, string> key => value
     */
    public static function findAllActiveForClang(int $clangId): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT s.key_name, sv.value FROM ' . \rex::getTable('snippets_string') . ' s ' .
            'INNER JOIN ' . \rex::getTable('snippets_string_value') . ' sv ON s.id = sv.string_id ' .
            'WHERE s.status = 1 AND sv.clang_id = ? AND sv.value IS NOT NULL AND sv.value != ""',
            [$clangId]
        );

        $result = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $result[(string) $sql->getValue('key_name')] = (string) $sql->getValue('value');
            $sql->next();
        }

        return $result;
    }

    /**
     * Prüft ob ein Key bereits existiert
     */
    public static function keyExists(string $key, int $excludeId = 0): bool
    {
        $sql = \rex_sql::factory();
        $query = 'SELECT id FROM ' . \rex::getTable('snippets_string') . ' WHERE key_name = ?';
        $params = [$key];

        if ($excludeId > 0) {
            $query .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $sql->setQuery($query, $params);
        return $sql->getRows() > 0;
    }
}
