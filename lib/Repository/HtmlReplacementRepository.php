<?php

/**
 * Snippets AddOn - HTML Replacement Repository
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets\Repository;

use FriendsOfREDAXO\Snippets\Domain\HtmlReplacement;
use rex;
use rex_sql;

/**
 * Repository für HTML-Ersetzungsregeln
 */
class HtmlReplacementRepository
{
    /**
     * Findet alle Regeln
     *
     * @return HtmlReplacement[]
     */
    public static function findAll(bool $onlyActive = false): array
    {
        try {
            $sql = rex_sql::factory();
            $query = 'SELECT * FROM ' . rex::getTable('snippets_html_replacement');
            
            if ($onlyActive) {
                $query .= ' WHERE status = 1';
            }
            
            $query .= ' ORDER BY priority DESC, name ASC';
            
            $sql->setQuery($query);
            
            $replacements = [];
            for ($i = 0; $i < $sql->getRows(); ++$i) {
                $data = [];
                foreach ($sql->getFieldnames() as $fieldname) {
                    $data[$fieldname] = $sql->getValue($fieldname);
                }
                $replacements[] = HtmlReplacement::fromArray($data);
                $sql->next();
            }
            
            return $replacements;
        } catch (\Exception $e) {
            // Tabelle existiert nicht oder andere DB-Fehler
            return [];
        }
    }

    /**
     * Findet Regel nach ID
     */
    public static function findById(int $id): ?HtmlReplacement
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('snippets_html_replacement') . ' WHERE id = ?',
            [$id]
        );
        
        if (0 === $sql->getRows()) {
            return null;
        }
        
        $data = [];
        foreach ($sql->getFieldnames() as $fieldname) {
            $data[$fieldname] = $sql->getValue($fieldname);
        }
        
        return HtmlReplacement::fromArray($data);
    }

    /**
     * Findet aktive Regeln für Kontext
     *
     * @return HtmlReplacement[]
     */
    public static function findActiveForContext(string $context): array
    {
        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'SELECT * FROM ' . rex::getTable('snippets_html_replacement') . ' 
                WHERE status = 1 
                AND (scope_context = ? OR scope_context = ?)
                ORDER BY priority DESC, name ASC',
                [$context, HtmlReplacement::CONTEXT_BOTH]
            );
            
            $replacements = [];
            for ($i = 0; $i < $sql->getRows(); ++$i) {
                $data = [];
                foreach ($sql->getFieldnames() as $fieldname) {
                    $data[$fieldname] = $sql->getValue($fieldname);
                }
                $replacements[] = HtmlReplacement::fromArray($data);
                $sql->next();
            }
            
            return $replacements;
        } catch (\Exception $e) {
            // Fehler beim Laden
            return [];
        }
    }

    /**
     * Speichert eine Regel
     */
    public static function save(array $data): int
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_html_replacement'));

        // JSON-Encoding für Arrays
        if (isset($data['scope_templates']) && is_array($data['scope_templates'])) {
            $data['scope_templates'] = json_encode(array_values($data['scope_templates']));
        }
        if (isset($data['scope_backend_pages']) && is_array($data['scope_backend_pages'])) {
            $data['scope_backend_pages'] = json_encode(array_values($data['scope_backend_pages']));
        }
        if (isset($data['scope_categories']) && is_array($data['scope_categories'])) {
            $data['scope_categories'] = json_encode(array_values($data['scope_categories']));
        }

        // ID vorhanden = Update
        if (isset($data['id']) && $data['id'] > 0) {
            $sql->setWhere(['id' => $data['id']]);
            $sql->setValues($data);
            $sql->update();
            return (int) $data['id'];
        }

        // Neu erstellen
        $sql->setValues($data);
        $sql->insert();
        return (int) $sql->getLastId();
    }

    /**
     * Löscht eine Regel
     */
    public static function delete(int $id): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_html_replacement'));
        $sql->setWhere(['id' => $id]);
        $sql->delete();
        
        return $sql->getRows() > 0;
    }

    /**
     * Ändert Status einer Regel
     */
    public static function toggleStatus(int $id): bool
    {
        $replacement = self::findById($id);
        if (null === $replacement) {
            return false;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_html_replacement'));
        $sql->setWhere(['id' => $id]);
        $sql->setValue('status', $replacement->isActive() ? 0 : 1);
        $sql->update();

        return true;
    }

    /**
     * Zählt alle Regeln
     */
    public static function count(bool $onlyActive = false): int
    {
        $sql = rex_sql::factory();
        $query = 'SELECT COUNT(*) as count FROM ' . rex::getTable('snippets_html_replacement');
        
        if ($onlyActive) {
            $query .= ' WHERE status = 1';
        }
        
        $sql->setQuery($query);
        
        return (int) $sql->getValue('count');
    }

    /**
     * Prüft ob Name bereits existiert
     */
    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = rex_sql::factory();
        $query = 'SELECT COUNT(*) as count FROM ' . rex::getTable('snippets_html_replacement') . ' WHERE name = ?';
        $params = [$name];
        
        if (null !== $excludeId) {
            $query .= ' AND id != ?';
            $params[] = $excludeId;
        }
        
        $sql->setQuery($query, $params);
        
        return $sql->getValue('count') > 0;
    }
}
