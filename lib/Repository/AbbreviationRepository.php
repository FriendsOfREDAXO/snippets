<?php

/**
 * Snippets AddOn - Abbreviation Repository
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets\Repository;

use FriendsOfREDAXO\Snippets\Domain\Abbreviation;
use rex;
use rex_sql;

/**
 * Repository für Abkürzungen/Akronyme
 */
class AbbreviationRepository
{
    /**
     * Findet alle Abkürzungen
     *
     * @return Abbreviation[]
     */
    public static function findAll(bool $onlyActive = false): array
    {
        try {
            $sql = rex_sql::factory();
            $query = 'SELECT * FROM ' . rex::getTable('snippets_abbreviation');
            
            if ($onlyActive) {
                $query .= ' WHERE status = 1';
            }
            
            $query .= ' ORDER BY priority DESC, abbr ASC';
            
            $sql->setQuery($query);
            
            $abbreviations = [];
            for ($i = 0; $i < $sql->getRows(); ++$i) {
                $data = [];
                foreach ($sql->getFieldnames() as $fieldname) {
                    $data[$fieldname] = $sql->getValue($fieldname);
                }
                $abbreviations[] = Abbreviation::fromArray($data);
                $sql->next();
            }
            
            return $abbreviations;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Findet Abkürzung nach ID
     */
    public static function findById(int $id): ?Abbreviation
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('snippets_abbreviation') . ' WHERE id = ?',
            [$id]
        );
        
        if (0 === $sql->getRows()) {
            return null;
        }
        
        $data = [];
        foreach ($sql->getFieldnames() as $fieldname) {
            $data[$fieldname] = $sql->getValue($fieldname);
        }
        
        return Abbreviation::fromArray($data);
    }

    /**
     * Findet aktive Abkürzungen für Kontext und Sprache
     *
     * @return Abbreviation[]
     */
    public static function findActiveForContext(string $context, int $clangId): array
    {
        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'SELECT * FROM ' . rex::getTable('snippets_abbreviation') . ' 
                WHERE status = 1 
                AND (scope_context = ? OR scope_context = ?)
                ORDER BY priority DESC, abbr ASC',
                [$context, Abbreviation::CONTEXT_BOTH]
            );
            
            $abbreviations = [];
            for ($i = 0; $i < $sql->getRows(); ++$i) {
                $data = [];
                foreach ($sql->getFieldnames() as $fieldname) {
                    $data[$fieldname] = $sql->getValue($fieldname);
                }
                $abbr = Abbreviation::fromArray($data);
                
                // Sprachprüfung
                if ($abbr->appliesToLanguage($clangId)) {
                    $abbreviations[] = $abbr;
                }
                
                $sql->next();
            }
            
            return $abbreviations;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Speichert eine Abkürzung
     */
    public static function save(array $data): int
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_abbreviation'));
        
        // Arrays zu JSON konvertieren
        if (isset($data['scope_templates']) && is_array($data['scope_templates'])) {
            $data['scope_templates'] = json_encode(array_values(array_filter($data['scope_templates'])));
        }
        if (isset($data['scope_categories']) && is_array($data['scope_categories'])) {
            $data['scope_categories'] = json_encode(array_values(array_filter($data['scope_categories'])));
        }
        
        // Booleans konvertieren
        if (isset($data['case_sensitive'])) {
            $data['case_sensitive'] = (int) $data['case_sensitive'];
        }
        if (isset($data['whole_word'])) {
            $data['whole_word'] = (int) $data['whole_word'];
        }
        if (isset($data['status'])) {
            $data['status'] = (int) $data['status'];
        }
        
        // Leere Werte filtern
        $data = array_filter($data, fn($value) => null !== $value && '' !== $value);
        
        if (isset($data['id']) && $data['id'] > 0) {
            $sql->setWhere(['id' => $data['id']]);
            $sql->setValues($data);
            $sql->update();
            return (int) $data['id'];
        }
        
        $sql->setValues($data);
        $sql->insert();
        return (int) $sql->getLastId();
    }

    /**
     * Löscht eine Abkürzung
     */
    public static function delete(int $id): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_abbreviation'));
        $sql->setWhere(['id' => $id]);
        $sql->delete();
        
        return $sql->getRows() > 0;
    }

    /**
     * Schaltet Status um
     */
    public static function toggleStatus(int $id): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE ' . rex::getTable('snippets_abbreviation') . ' 
            SET status = 1 - status 
            WHERE id = ?',
            [$id]
        );
        
        return true;
    }

    /**
     * Setzt Status direkt
     */
    public static function updateStatus(int $id, int $status): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_abbreviation'));
        $sql->setWhere(['id' => $id]);
        $sql->setValue('status', $status);
        $sql->update();
        
        return true;
    }

    /**
     * Zählt Abkürzungen
     */
    public static function count(bool $onlyActive = false): int
    {
        $sql = rex_sql::factory();
        $query = 'SELECT COUNT(*) as count FROM ' . rex::getTable('snippets_abbreviation');
        
        if ($onlyActive) {
            $query .= ' WHERE status = 1';
        }
        
        $sql->setQuery($query);
        
        return (int) $sql->getValue('count');
    }

    /**
     * Prüft ob Abkürzung bereits existiert und gibt deren ID zurück
     *
     * @param string $abbr Die Abkürzung
     * @param int|null $language Sprache (0 oder null = alle Sprachen)
     * @param int|null $excludeId ID zum Ausschließen (für Updates)
     * @return int 0 wenn nicht existiert, ansonsten die ID
     */
    public static function exists(string $abbr, ?int $language = null, ?int $excludeId = null): int
    {
        $sql = rex_sql::factory();
        $query = 'SELECT id FROM ' . rex::getTable('snippets_abbreviation') . ' WHERE abbr = ?';
        $params = [$abbr];

        // Sprachprüfung: Entweder gleiche Sprache oder global (null/0)
        if (null !== $language && $language > 0) {
            $query .= ' AND (language = ? OR language IS NULL OR language = 0)';
            $params[] = $language;
        }

        if (null !== $excludeId && $excludeId > 0) {
            $query .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $sql->setQuery($query, $params);

        if ($sql->getRows() > 0) {
            return (int) $sql->getValue('id');
        }

        return 0;
    }
}
