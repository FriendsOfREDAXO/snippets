<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Repository\HtmlReplacementRepository;
use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;

/**
 * Installer-API für andere AddOns
 *
 * Erlaubt es AddOns, eigene Snippets, Übersetzungen (Translations),
 * HTML-Ersetzungen und Abkürzungen programmatisch zu installieren,
 * aktualisieren und zu entfernen.
 *
 * Verwendung in install.php oder boot.php eines AddOns:
 *
 *   use FriendsOfREDAXO\Snippets\Service\SnippetsInstaller;
 *
 *   SnippetsInstaller::installTranslations([
 *       'my_addon.greeting' => ['de' => 'Hallo', 'en' => 'Hello'],
 *       'my_addon.farewell' => ['de' => 'Tschüss', 'en' => 'Goodbye'],
 *   ]);
 *
 * @package redaxo\snippets
 */
class SnippetsInstaller
{
    /** Bestehende Einträge überspringen */
    public const SKIP = 'skip';

    /** Bestehende Einträge komplett überschreiben */
    public const OVERWRITE = 'overwrite';

    /** Nur leere Sprachwerte füllen, bestehende Werte nicht überschreiben */
    public const FILL_EMPTY = 'fill_empty';

    // ---------------------------------------------------------------
    // Translations
    // ---------------------------------------------------------------

    /**
     * Installiert String-Übersetzungen (Translations).
     *
     * Sprachen werden per Code (de, en, fr, …) angegeben – nicht per ID.
     * Dadurch funktioniert der Code in jeder REDAXO-Installation.
     *
     * @param array<string, array<string, string>> $items  key => [lang_code => value, …]
     * @param string $conflictMode self::SKIP | self::OVERWRITE | self::FILL_EMPTY
     * @param string|null $category Optionaler Kategoriename für alle Einträge
     * @param string $categoryIcon Font-Awesome Icon (nur bei neuer Kategorie)
     * @return array{imported: int, skipped: int, updated: int}
     */
    public static function installTranslations(
        array $items,
        string $conflictMode = self::SKIP,
        ?string $category = null,
        string $categoryIcon = 'fa-puzzle-piece',
    ): array {
        $clangsByCode = self::buildClangMap();
        $categoryId = self::resolveCategoryId($category, $categoryIcon);

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($items as $key => $translations) {
            $key = (string) $key;
            if ('' === $key) {
                ++$skipped;
                continue;
            }

            $existing = TranslationStringRepository::getByKey($key);

            if (null !== $existing) {
                if (self::SKIP === $conflictMode) {
                    ++$skipped;
                    continue;
                }

                $stringId = $existing->getId();

                if (self::OVERWRITE === $conflictMode) {
                    // Alle Werte überschreiben
                    foreach ($translations as $langCode => $value) {
                        $clangId = $clangsByCode[(string) $langCode] ?? null;
                        if (null !== $clangId) {
                            TranslationStringRepository::saveValue($stringId, $clangId, $value);
                        }
                    }
                    ++$updated;
                } elseif (self::FILL_EMPTY === $conflictMode) {
                    // Nur leere Werte füllen
                    $existingValues = TranslationStringRepository::loadValues($stringId);
                    foreach ($translations as $langCode => $value) {
                        $clangId = $clangsByCode[(string) $langCode] ?? null;
                        if (null === $clangId) {
                            continue;
                        }
                        $existingValue = $existingValues[$clangId] ?? '';
                        if ('' === $existingValue) {
                            TranslationStringRepository::saveValue($stringId, $clangId, $value);
                        }
                    }
                    ++$updated;
                }

                // Kategorie aktualisieren wenn angegeben
                if (null !== $categoryId) {
                    TranslationStringRepository::save([
                        'id' => $stringId,
                        'key_name' => $key,
                        'category_id' => $categoryId,
                        'status' => $existing->isActive() ? 1 : 0,
                    ]);
                }
            } else {
                // Neuen Eintrag erstellen
                $data = [
                    'key_name' => $key,
                    'category_id' => $categoryId ?? 0,
                    'status' => 1,
                ];

                $stringId = TranslationStringRepository::save($data);

                foreach ($translations as $langCode => $value) {
                    $clangId = $clangsByCode[(string) $langCode] ?? null;
                    if (null !== $clangId) {
                        TranslationStringRepository::saveValue($stringId, $clangId, $value);
                    }
                }

                ++$imported;
            }
        }

        SnippetsTranslate::clearCache();

        return ['imported' => $imported, 'skipped' => $skipped, 'updated' => $updated];
    }

    /**
     * Entfernt Übersetzungen anhand eines Key-Prefix.
     *
     * Nützlich beim Deinstallieren eines AddOns:
     *   SnippetsInstaller::removeTranslationsByPrefix('my_addon.');
     *
     * @return array{removed: int}
     */
    public static function removeTranslationsByPrefix(string $prefix): array
    {
        if ('' === $prefix) {
            return ['removed' => 0];
        }

        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . \rex::getTable('snippets_string') . ' WHERE key_name LIKE ?',
            [$prefix . '%'],
        );

        $removed = 0;
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            TranslationStringRepository::delete((int) $sql->getValue('id'));
            ++$removed;
            $sql->next();
        }

        if ($removed > 0) {
            SnippetsTranslate::clearCache();
        }

        return ['removed' => $removed];
    }

    /**
     * Entfernt einzelne Übersetzungen anhand ihrer Keys.
     *
     * @param array<string> $keys
     * @return array{removed: int}
     */
    public static function removeTranslationsByKeys(array $keys): array
    {
        $removed = 0;

        foreach ($keys as $key) {
            $existing = TranslationStringRepository::getByKey($key);
            if (null !== $existing) {
                TranslationStringRepository::delete($existing->getId());
                ++$removed;
            }
        }

        if ($removed > 0) {
            SnippetsTranslate::clearCache();
        }

        return ['removed' => $removed];
    }

    // ---------------------------------------------------------------
    // Snippets
    // ---------------------------------------------------------------

    /**
     * Installiert Snippets.
     *
     * @param array<string, array{content: string, title?: string, description?: string, content_type?: string, context?: string, status?: int}> $items
     *        key => [content => '...', title => '...', ...]
     * @param string $conflictMode self::SKIP | self::OVERWRITE
     * @return array{imported: int, skipped: int, updated: int}
     */
    public static function installSnippets(
        array $items,
        string $conflictMode = self::SKIP,
    ): array {
        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($items as $key => $item) {
            $key = (string) $key;
            if ('' === $key) {
                ++$skipped;
                continue;
            }

            $existing = SnippetRepository::getByKey($key);

            if (null !== $existing) {
                if (self::SKIP === $conflictMode) {
                    ++$skipped;
                    continue;
                }

                // Overwrite
                $data = [
                    'id' => $existing->getId(),
                    'key_name' => $key,
                    'title' => $item['title'] ?? $existing->getTitle(),
                    'description' => $item['description'] ?? ($existing->getDescription() ?? ''),
                    'content' => $item['content'] ?? ($existing->getContent() ?? ''),
                    'content_type' => $item['content_type'] ?? $existing->getContentType(),
                    'context' => $item['context'] ?? $existing->getContext(),
                    'status' => (int) ($item['status'] ?? ($existing->isActive() ? 1 : 0)),
                ];

                SnippetRepository::save($data);
                ++$updated;
            } else {
                $data = [
                    'key_name' => $key,
                    'title' => $item['title'] ?? $key,
                    'description' => $item['description'] ?? '',
                    'content' => $item['content'] ?? '',
                    'content_type' => $item['content_type'] ?? 'html',
                    'context' => $item['context'] ?? 'frontend',
                    'status' => (int) ($item['status'] ?? 1),
                ];

                SnippetRepository::save($data);
                ++$imported;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'updated' => $updated];
    }

    /**
     * Entfernt Snippets anhand eines Key-Prefix.
     *
     * @return array{removed: int}
     */
    public static function removeSnippetsByPrefix(string $prefix): array
    {
        if ('' === $prefix) {
            return ['removed' => 0];
        }

        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . \rex::getTable('snippets_snippet') . ' WHERE key_name LIKE ?',
            [$prefix . '%'],
        );

        $removed = 0;
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            SnippetRepository::delete((int) $sql->getValue('id'));
            ++$removed;
            $sql->next();
        }

        return ['removed' => $removed];
    }

    /**
     * Entfernt einzelne Snippets anhand ihrer Keys.
     *
     * @param array<string> $keys
     * @return array{removed: int}
     */
    public static function removeSnippetsByKeys(array $keys): array
    {
        $removed = 0;

        foreach ($keys as $key) {
            $existing = SnippetRepository::getByKey($key);
            if (null !== $existing) {
                SnippetRepository::delete($existing->getId());
                ++$removed;
            }
        }

        return ['removed' => $removed];
    }

    // ---------------------------------------------------------------
    // HTML-Ersetzungen
    // ---------------------------------------------------------------

    /**
     * Installiert HTML-Ersetzungen.
     *
     * @param array<string, array{type: string, search_value: string, replacement: string, description?: string, position?: string, scope_context?: string, priority?: int, status?: int}> $items
     *        name => [type => '...', search_value => '...', replacement => '...', ...]
     * @param string $conflictMode self::SKIP | self::OVERWRITE
     * @return array{imported: int, skipped: int, updated: int}
     */
    public static function installHtmlReplacements(
        array $items,
        string $conflictMode = self::SKIP,
    ): array {
        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($items as $name => $item) {
            $name = (string) $name;
            if ('' === $name) {
                ++$skipped;
                continue;
            }

            $existingId = HtmlReplacementRepository::nameExists($name);

            if ($existingId > 0) {
                if (self::SKIP === $conflictMode) {
                    ++$skipped;
                    continue;
                }

                $item['id'] = $existingId;
                $item['name'] = $name;
                HtmlReplacementRepository::save($item);
                ++$updated;
            } else {
                $item['name'] = $name;
                $item['status'] = (int) ($item['status'] ?? 1);
                $item['priority'] = (int) ($item['priority'] ?? 10);
                $item['position'] = $item['position'] ?? 'replace';
                $item['scope_context'] = $item['scope_context'] ?? 'frontend';
                HtmlReplacementRepository::save($item);
                ++$imported;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'updated' => $updated];
    }

    /**
     * Entfernt HTML-Ersetzungen anhand eines Namens-Prefix.
     *
     * @return array{removed: int}
     */
    public static function removeHtmlReplacementsByPrefix(string $prefix): array
    {
        if ('' === $prefix) {
            return ['removed' => 0];
        }

        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . \rex::getTable('snippets_html_replacement') . ' WHERE name LIKE ?',
            [$prefix . '%'],
        );

        $removed = 0;
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            HtmlReplacementRepository::delete((int) $sql->getValue('id'));
            ++$removed;
            $sql->next();
        }

        return ['removed' => $removed];
    }

    // ---------------------------------------------------------------
    // JSON-Datei Import
    // ---------------------------------------------------------------

    /**
     * Installiert Daten aus einer JSON-Export-Datei.
     *
     * Erlaubt das Laden der exportierten Dateien direkt in install.php oder update.php.
     *
     * @param string $filepath Absoluter Pfad zur JSON-Datei
     * @param string $conflictMode self::SKIP | self::OVERWRITE | self::FILL_EMPTY (nur Translations)
     * @return array{success: bool, imported?: int, skipped?: int, error?: string}
     */
    public static function installFromFile(string $filepath, string $conflictMode = self::SKIP): array
    {
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'File not found: ' . $filepath];
        }

        $json = \rex_file::get($filepath);
        if (null === $json || '' === $json) {
            return ['success' => false, 'error' => 'Could not read file: ' . $filepath];
        }

        $data = json_decode($json, true);
        if (null === $data || !isset($data['type'], $data['items'])) {
            return ['success' => false, 'error' => 'Invalid JSON format'];
        }

        // Sprachmapping für Translations
        $languageMapping = null;
        if (ImportExportService::TYPE_TRANSLATIONS === $data['type']) {
            $languageMapping = [];
            foreach (\rex_clang::getAll() as $clang) {
                $languageMapping[$clang->getCode()] = $clang->getId();
            }
        }

        $overwrite = self::SKIP !== $conflictMode;

        return ImportExportService::import($json, $overwrite, $languageMapping);
    }

    // ---------------------------------------------------------------
    // Prüf-Methoden
    // ---------------------------------------------------------------

    /**
     * Prüft ob ein Übersetzungs-Key existiert.
     */
    public static function translationExists(string $key): bool
    {
        return TranslationStringRepository::keyExists($key);
    }

    /**
     * Prüft ob ein Snippet-Key existiert.
     */
    public static function snippetExists(string $key): bool
    {
        return null !== SnippetRepository::getByKey($key);
    }

    // ---------------------------------------------------------------
    // Hilfsmethoden
    // ---------------------------------------------------------------

    /**
     * Baut ein Mapping: clang_code → clang_id
     *
     * @return array<string, int>
     */
    private static function buildClangMap(): array
    {
        $map = [];
        foreach (\rex_clang::getAll() as $clang) {
            $map[$clang->getCode()] = $clang->getId();
        }
        return $map;
    }

    /**
     * Löst einen Kategorienamen auf eine ID auf.
     * Erstellt die Kategorie wenn nötig.
     */
    private static function resolveCategoryId(?string $category, string $icon = 'fa-puzzle-piece'): ?int
    {
        if (null === $category || '' === $category) {
            return null;
        }

        // Kategorie per Name suchen
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . \rex::getTable('snippets_category') . ' WHERE name = ?',
            [$category],
        );

        if ($sql->getRows() > 0) {
            return (int) $sql->getValue('id');
        }

        // Kategorie erstellen
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('snippets_category'));
        $sql->setValue('name', $category);
        $sql->setValue('icon', $icon);
        $sql->setValue('sort_order', 100);
        $sql->insert();

        return (int) $sql->getLastId();
    }
}
