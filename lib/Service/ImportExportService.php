<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Repository\HtmlReplacementRepository;
use FriendsOfREDAXO\Snippets\Repository\AbbreviationRepository;
use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;

/**
 * Service für Import/Export von Snippets, HTML-Ersetzungen, Abkürzungen und Übersetzungen
 *
 * @package redaxo\snippets
 */
class ImportExportService
{
    public const TYPE_SNIPPETS = 'snippets';
    public const TYPE_HTML_REPLACEMENTS = 'html_replacements';
    public const TYPE_ABBREVIATIONS = 'abbreviations';
    public const TYPE_TRANSLATIONS = 'translations';

    /**
     * Exportiert Snippets als JSON
     *
     * @param array<int>|null $ids Nur bestimmte IDs exportieren, null = alle
     * @return array{success: bool, data?: string, error?: string}
     */
    public static function exportSnippets(?array $ids = null): array
    {
        try {
            $snippets = SnippetRepository::findAll();

            if (null !== $ids) {
                $snippets = array_filter($snippets, static fn($s) => in_array($s->getId(), $ids, true));
            }

            $exportData = [
                'type' => self::TYPE_SNIPPETS,
                'version' => '1.0',
                'exported_at' => date('c'),
                'count' => count($snippets),
                'items' => [],
            ];

            foreach ($snippets as $snippet) {
                $item = [
                    'key_name' => $snippet->getKey(),
                    'title' => $snippet->getTitle(),
                    'description' => $snippet->getDescription(),
                    'content' => $snippet->getContent(),
                    'content_type' => $snippet->getContentType(),
                    'context' => $snippet->getContext(),
                    'status' => $snippet->isActive() ? 1 : 0,
                    'is_multilang' => $snippet->isMultilang() ? 1 : 0,
                ];

                // Übersetzungen exportieren wenn multilang
                if ($snippet->isMultilang()) {
                    $translations = self::getSnippetTranslations($snippet->getId());
                    if ([] !== $translations) {
                        $item['translations'] = $translations;
                    }
                }

                $exportData['items'][] = $item;
            }

            $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (false === $json) {
                return ['success' => false, 'error' => 'JSON encoding failed'];
            }

            return ['success' => true, 'data' => $json];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Exportiert HTML-Ersetzungen als JSON
     *
     * @param array<int>|null $ids Nur bestimmte IDs exportieren, null = alle
     * @return array{success: bool, data?: string, error?: string}
     */
    public static function exportHtmlReplacements(?array $ids = null): array
    {
        try {
            $replacements = HtmlReplacementRepository::findAll();

            if (null !== $ids) {
                $replacements = array_filter($replacements, static fn($r) => in_array($r->getId(), $ids, true));
            }

            $exportData = [
                'type' => self::TYPE_HTML_REPLACEMENTS,
                'version' => '1.0',
                'exported_at' => date('c'),
                'count' => count($replacements),
                'items' => [],
            ];

            foreach ($replacements as $replacement) {
                $exportData['items'][] = [
                    'name' => $replacement->getName(),
                    'description' => $replacement->getDescription(),
                    'type' => $replacement->getType(),
                    'search_value' => $replacement->getSearchValue(),
                    'replacement' => $replacement->getReplacement(),
                    'position' => $replacement->getPosition(),
                    'scope_context' => $replacement->getScopeContext(),
                    'scope_templates' => $replacement->getScopeTemplates(),
                    'scope_backend_pages' => $replacement->getScopeBackendPages(),
                    'scope_backend_request_pattern' => $replacement->getScopeBackendRequestPattern(),
                    'scope_categories' => $replacement->getScopeCategories(),
                    'scope_url_pattern' => $replacement->getScopeUrlPattern(),
                    'priority' => $replacement->getPriority(),
                    'status' => $replacement->isActive() ? 1 : 0,
                ];
            }

            $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (false === $json) {
                return ['success' => false, 'error' => 'JSON encoding failed'];
            }

            return ['success' => true, 'data' => $json];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Exportiert Abkürzungen als JSON
     *
     * @param array<int>|null $ids Nur bestimmte IDs exportieren, null = alle
     * @return array{success: bool, data?: string, error?: string}
     */
    public static function exportAbbreviations(?array $ids = null): array
    {
        try {
            $abbreviations = AbbreviationRepository::findAll();

            if (null !== $ids) {
                $abbreviations = array_filter($abbreviations, static fn($a) => in_array($a->getId(), $ids, true));
            }

            $exportData = [
                'type' => self::TYPE_ABBREVIATIONS,
                'version' => '1.0',
                'exported_at' => date('c'),
                'count' => count($abbreviations),
                'items' => [],
            ];

            foreach ($abbreviations as $abbreviation) {
                $exportData['items'][] = [
                    'abbr' => $abbreviation->getAbbr(),
                    'title' => $abbreviation->getTitle(),
                    'description' => $abbreviation->getDescription(),
                    'language' => $abbreviation->getLanguage(),
                    'case_sensitive' => $abbreviation->isCaseSensitive() ? 1 : 0,
                    'whole_word' => $abbreviation->isWholeWord() ? 1 : 0,
                    'scope_context' => $abbreviation->getScopeContext(),
                    'priority' => $abbreviation->getPriority(),
                    'status' => $abbreviation->isActive() ? 1 : 0,
                ];
            }

            $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (false === $json) {
                return ['success' => false, 'error' => 'JSON encoding failed'];
            }

            return ['success' => true, 'data' => $json];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Exportiert Übersetzungen (String-Translations) als JSON
     *
     * Sprachen werden per Code (de, en, fr) exportiert statt per ID,
     * damit der Export zwischen verschiedenen REDAXO-Installationen portabel ist.
     *
     * @param array<int>|null $ids Nur bestimmte IDs exportieren, null = alle
     * @return array{success: bool, data?: string, error?: string}
     */
    public static function exportTranslations(?array $ids = null): array
    {
        try {
            $strings = TranslationStringRepository::findAll();

            if (null !== $ids) {
                $strings = array_filter($strings, static fn ($s) => in_array($s->getId(), $ids, true));
            }

            // Sprach-Code-Mapping: clang_id → code
            $clangCodes = [];
            foreach (\rex_clang::getAll() as $clang) {
                $clangCodes[$clang->getId()] = $clang->getCode();
            }

            // Kategorien laden
            $categories = self::loadCategories();

            $exportData = [
                'type' => self::TYPE_TRANSLATIONS,
                'version' => '1.0',
                'exported_at' => date('c'),
                'languages' => array_values($clangCodes),
                'count' => count($strings),
                'items' => [],
            ];

            foreach ($strings as $string) {
                $item = [
                    'key' => $string->getKey(),
                    'status' => $string->isActive() ? 1 : 0,
                ];

                // Kategorie per Name exportieren (nicht per ID)
                $catId = $string->getCategoryId();
                if (null !== $catId && $catId > 0 && isset($categories[$catId])) {
                    $item['category'] = $categories[$catId]['name'];
                    $catIcon = trim($categories[$catId]['icon'] ?? '');
                    if ('' !== $catIcon) {
                        $item['category_icon'] = $catIcon;
                    }
                }

                // Werte per Sprach-Code statt ID
                $values = $string->getValues();
                $translations = [];
                foreach ($values as $clangId => $value) {
                    if (isset($clangCodes[$clangId]) && '' !== $value) {
                        $translations[$clangCodes[$clangId]] = $value;
                    }
                }
                $item['translations'] = $translations;

                $exportData['items'][] = $item;
            }

            $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (false === $json) {
                return ['success' => false, 'error' => 'JSON encoding failed'];
            }

            return ['success' => true, 'data' => $json];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analysiert eine Import-Datei und gibt Vorschau/Konfliktinfo zurück
     *
     * Wird VOR dem tatsächlichen Import aufgerufen, um dem Benutzer
     * eine Übersicht mit Sprachmapping und Konflikten zu zeigen.
     *
     * @return array{valid: bool, type?: string, count?: int, languages?: array<string, array{code: string, mapped: bool, clang_id: ?int, clang_name: ?string}>, new_keys?: int, existing_keys?: int, categories?: array<string>, error?: string}
     */
    public static function analyzeImport(string $json): array
    {
        $data = json_decode($json, true);

        if (null === $data) {
            return ['valid' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }

        if (!isset($data['type']) || !isset($data['items']) || !is_array($data['items'])) {
            return ['valid' => false, 'error' => 'Invalid export format'];
        }

        $validTypes = [self::TYPE_SNIPPETS, self::TYPE_HTML_REPLACEMENTS, self::TYPE_ABBREVIATIONS, self::TYPE_TRANSLATIONS];
        if (!in_array($data['type'], $validTypes, true)) {
            return ['valid' => false, 'error' => 'Unknown type: ' . $data['type']];
        }

        $result = [
            'valid' => true,
            'type' => $data['type'],
            'count' => count($data['items']),
            'exported_at' => $data['exported_at'] ?? null,
        ];

        // Typ-spezifische Analyse
        if (self::TYPE_TRANSLATIONS === $data['type']) {
            $result = array_merge($result, self::analyzeTranslationsImport($data));
        } elseif (self::TYPE_SNIPPETS === $data['type']) {
            $result = array_merge($result, self::analyzeSnippetsImport($data['items']));
        } elseif (self::TYPE_HTML_REPLACEMENTS === $data['type']) {
            $result = array_merge($result, self::analyzeHtmlReplacementsImport($data['items']));
        } elseif (self::TYPE_ABBREVIATIONS === $data['type']) {
            $result = array_merge($result, self::analyzeAbbreviationsImport($data['items']));
        }

        return $result;
    }

    /**
     * Analysiert Translations-Import: Sprachmapping und Konflikte
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function analyzeTranslationsImport(array $data): array
    {
        $items = $data['items'] ?? [];
        $exportedLangs = $data['languages'] ?? [];

        // Alle im Export vorkommenden Sprach-Codes sammeln
        $usedCodes = [];
        foreach ($items as $item) {
            if (isset($item['translations']) && is_array($item['translations'])) {
                foreach (array_keys($item['translations']) as $code) {
                    $usedCodes[(string) $code] = true;
                }
            }
        }

        // Merge mit languages-Header (falls vorhanden)
        foreach ($exportedLangs as $code) {
            $usedCodes[(string) $code] = true;
        }

        // Sprach-Mapping erstellen
        $clangsByCode = [];
        foreach (\rex_clang::getAll() as $clang) {
            $clangsByCode[$clang->getCode()] = $clang;
        }

        $languages = [];
        foreach (array_keys($usedCodes) as $code) {
            $mapped = isset($clangsByCode[$code]);
            $languages[$code] = [
                'code' => $code,
                'mapped' => $mapped,
                'clang_id' => $mapped ? $clangsByCode[$code]->getId() : null,
                'clang_name' => $mapped ? $clangsByCode[$code]->getName() : null,
            ];
        }

        // Bestehende vs. neue Keys zählen
        $newKeys = 0;
        $existingKeys = 0;
        foreach ($items as $item) {
            $key = $item['key'] ?? '';
            if ('' === $key) {
                continue;
            }
            if (TranslationStringRepository::keyExists($key)) {
                ++$existingKeys;
            } else {
                ++$newKeys;
            }
        }

        // Kategorien sammeln
        $categories = [];
        foreach ($items as $item) {
            if (isset($item['category']) && '' !== $item['category']) {
                $categories[$item['category']] = true;
            }
        }

        return [
            'languages' => $languages,
            'new_keys' => $newKeys,
            'existing_keys' => $existingKeys,
            'categories' => array_keys($categories),
        ];
    }

    /**
     * Analysiert Snippets-Import
     *
     * @param array<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function analyzeSnippetsImport(array $items): array
    {
        $newKeys = 0;
        $existingKeys = 0;
        foreach ($items as $item) {
            $key = $item['key_name'] ?? '';
            if ('' === $key) {
                continue;
            }
            $existing = SnippetRepository::getByKey($key);
            if (null !== $existing) {
                ++$existingKeys;
            } else {
                ++$newKeys;
            }
        }
        return ['new_keys' => $newKeys, 'existing_keys' => $existingKeys];
    }

    /**
     * Analysiert HTML-Replacements-Import
     *
     * @param array<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function analyzeHtmlReplacementsImport(array $items): array
    {
        $newKeys = 0;
        $existingKeys = 0;
        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            if ('' === $name) {
                continue;
            }
            $existingId = HtmlReplacementRepository::nameExists($name);
            if ($existingId > 0) {
                ++$existingKeys;
            } else {
                ++$newKeys;
            }
        }
        return ['new_keys' => $newKeys, 'existing_keys' => $existingKeys];
    }

    /**
     * Analysiert Abkürzungen-Import
     *
     * @param array<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function analyzeAbbreviationsImport(array $items): array
    {
        $newKeys = 0;
        $existingKeys = 0;
        foreach ($items as $item) {
            $abbr = $item['abbr'] ?? '';
            if ('' === $abbr) {
                continue;
            }
            $language = isset($item['language']) ? (int) $item['language'] : 0;
            $existingId = AbbreviationRepository::exists($abbr, $language);
            if ($existingId > 0) {
                ++$existingKeys;
            } else {
                ++$newKeys;
            }
        }
        return ['new_keys' => $newKeys, 'existing_keys' => $existingKeys];
    }

    /**
     * Importiert Daten aus JSON
     *
     * @param string $json JSON-String
     * @param bool $overwrite Bestehende Einträge überschreiben
     * @param array<string, int>|null $languageMapping Sprach-Mapping für Translations: source_code => target_clang_id
     * @return array{success: bool, imported?: int, skipped?: int, error?: string}
     */
    public static function import(string $json, bool $overwrite = false, ?array $languageMapping = null): array
    {
        try {
            $data = json_decode($json, true);

            if (null === $data) {
                return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
            }

            if (!isset($data['type']) || !isset($data['items']) || !is_array($data['items'])) {
                return ['success' => false, 'error' => 'Invalid export format'];
            }

            return match ($data['type']) {
                self::TYPE_SNIPPETS => self::importSnippets($data['items'], $overwrite),
                self::TYPE_HTML_REPLACEMENTS => self::importHtmlReplacements($data['items'], $overwrite),
                self::TYPE_ABBREVIATIONS => self::importAbbreviations($data['items'], $overwrite),
                self::TYPE_TRANSLATIONS => self::importTranslations($data['items'], $overwrite, $languageMapping),
                default => ['success' => false, 'error' => 'Unknown type: ' . $data['type']],
            };
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Importiert Snippets
     *
     * @param array<array<string, mixed>> $items
     * @return array{success: bool, imported: int, skipped: int}
     */
    private static function importSnippets(array $items, bool $overwrite): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $key = $item['key_name'] ?? '';
            if ('' === $key) {
                ++$skipped;
                continue;
            }

            // Prüfen ob Snippet existiert
            $existing = SnippetRepository::getByKey($key);

            if (null !== $existing && !$overwrite) {
                ++$skipped;
                continue;
            }

            $data = [
                'key_name' => $key,
                'title' => $item['title'] ?? $key,
                'description' => $item['description'] ?? '',
                'content' => $item['content'] ?? '',
                'content_type' => $item['content_type'] ?? 'html',
                'context' => $item['context'] ?? 'frontend',
                'status' => (int) ($item['status'] ?? 1),
                'is_multilang' => (int) ($item['is_multilang'] ?? 0),
            ];

            if (null !== $existing) {
                $data['id'] = $existing->getId();
            }

            $savedId = SnippetRepository::save($data);

            // Übersetzungen importieren
            if (isset($item['translations']) && is_array($item['translations'])) {
                foreach ($item['translations'] as $clangId => $content) {
                    self::saveSnippetTranslation($savedId, (int) $clangId, $content);
                }
            }

            ++$imported;
        }

        return ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Importiert HTML-Ersetzungen
     *
     * @param array<array<string, mixed>> $items
     * @return array{success: bool, imported: int, skipped: int}
     */
    private static function importHtmlReplacements(array $items, bool $overwrite): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            if ('' === $name) {
                ++$skipped;
                continue;
            }

            // Prüfen ob Name existiert
            $existingId = HtmlReplacementRepository::nameExists($name);

            if ($existingId > 0 && !$overwrite) {
                ++$skipped;
                continue;
            }

            $data = [
                'name' => $name,
                'description' => $item['description'] ?? '',
                'type' => $item['type'] ?? 'css_selector',
                'search_value' => $item['search_value'] ?? '',
                'replacement' => $item['replacement'] ?? '',
                'position' => $item['position'] ?? 'replace',
                'scope_context' => $item['scope_context'] ?? 'frontend',
                'scope_templates' => $item['scope_templates'] ?? [],
                'scope_backend_pages' => $item['scope_backend_pages'] ?? [],
                'scope_backend_request_pattern' => $item['scope_backend_request_pattern'] ?? '',
                'scope_categories' => $item['scope_categories'] ?? [],
                'scope_url_pattern' => $item['scope_url_pattern'] ?? '',
                'priority' => (int) ($item['priority'] ?? 10),
                'status' => (int) ($item['status'] ?? 1),
            ];

            if ($existingId > 0) {
                $data['id'] = $existingId;
            }

            HtmlReplacementRepository::save($data);
            ++$imported;
        }

        return ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Importiert Abkürzungen
     *
     * @param array<array<string, mixed>> $items
     * @return array{success: bool, imported: int, skipped: int}
     */
    private static function importAbbreviations(array $items, bool $overwrite): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $abbr = $item['abbr'] ?? '';
            if ('' === $abbr) {
                ++$skipped;
                continue;
            }

            $language = isset($item['language']) ? (int) $item['language'] : 0;

            // Prüfen ob Abkürzung existiert
            $existingId = AbbreviationRepository::exists($abbr, $language);

            if ($existingId > 0 && !$overwrite) {
                ++$skipped;
                continue;
            }

            $data = [
                'abbr' => $abbr,
                'title' => $item['title'] ?? '',
                'description' => $item['description'] ?? '',
                'language' => $language > 0 ? $language : null,
                'case_sensitive' => (bool) ($item['case_sensitive'] ?? false),
                'whole_word' => (bool) ($item['whole_word'] ?? true),
                'scope_context' => $item['scope_context'] ?? 'frontend',
                'priority' => (int) ($item['priority'] ?? 50),
                'status' => (int) ($item['status'] ?? 1),
            ];

            if ($existingId > 0) {
                $data['id'] = $existingId;
            }

            AbbreviationRepository::save($data);
            ++$imported;
        }

        return ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Importiert Übersetzungen (String-Translations)
     *
     * Unterstützt Sprachmapping: Sprach-Codes aus dem Export werden auf
     * lokale clang_ids gemappt. Nicht gemappte Sprachen werden ignoriert.
     *
     * @param array<array<string, mixed>> $items
     * @param bool $overwrite Bestehende Keys überschreiben
     * @param array<string, int>|null $languageMapping source_code => target_clang_id, null = Auto-Mapping per Code
     * @return array{success: bool, imported: int, skipped: int, languages_mapped?: int, languages_skipped?: int}
     */
    private static function importTranslations(array $items, bool $overwrite, ?array $languageMapping = null): array
    {
        // Auto-Mapping wenn kein Mapping übergeben
        if (null === $languageMapping) {
            $languageMapping = [];
            foreach (\rex_clang::getAll() as $clang) {
                $languageMapping[$clang->getCode()] = $clang->getId();
            }
        }

        // Kategorien-Cache aufbauen (name → id)
        $existingCategories = self::loadCategories();
        $categoryNameMap = [];
        foreach ($existingCategories as $catId => $catData) {
            $categoryNameMap[mb_strtolower($catData['name'])] = $catId;
        }

        $imported = 0;
        $skipped = 0;
        $langsMapped = count($languageMapping);
        $langsSkipped = 0;

        foreach ($items as $item) {
            $key = $item['key'] ?? '';
            if ('' === $key) {
                ++$skipped;
                continue;
            }

            // Prüfen ob Key existiert
            $existing = TranslationStringRepository::getByKey($key);

            if (null !== $existing && !$overwrite) {
                ++$skipped;
                continue;
            }

            // Kategorie auflösen (per Name)
            $categoryId = null;
            if (isset($item['category']) && '' !== $item['category']) {
                $catNameLower = mb_strtolower($item['category']);
                if (isset($categoryNameMap[$catNameLower])) {
                    $categoryId = $categoryNameMap[$catNameLower];
                } else {
                    // Kategorie erstellen
                    $categoryId = self::createCategory(
                        $item['category'],
                        $item['category_icon'] ?? 'fa-folder'
                    );
                    $categoryNameMap[$catNameLower] = $categoryId;
                }
            }

            // String speichern
            $data = [
                'key_name' => $key,
                'category_id' => $categoryId ?? 0,
                'status' => (int) ($item['status'] ?? 1),
            ];

            if (null !== $existing) {
                $data['id'] = $existing->getId();
            }

            $stringId = TranslationStringRepository::save($data);

            // Werte per Sprach-Mapping speichern
            if (isset($item['translations']) && is_array($item['translations'])) {
                foreach ($item['translations'] as $langCode => $value) {
                    $langCode = (string) $langCode;
                    if (isset($languageMapping[$langCode])) {
                        $clangId = $languageMapping[$langCode];
                        TranslationStringRepository::saveValue($stringId, $clangId, $value);
                    }
                }
            }

            ++$imported;
        }

        // Zählen wieviele Sprachen nicht gemappt werden konnten
        $allExportCodes = [];
        foreach ($items as $item) {
            if (isset($item['translations']) && is_array($item['translations'])) {
                foreach (array_keys($item['translations']) as $code) {
                    $allExportCodes[(string) $code] = true;
                }
            }
        }
        foreach (array_keys($allExportCodes) as $code) {
            if (!isset($languageMapping[$code])) {
                ++$langsSkipped;
            }
        }

        SnippetsTranslate::clearCache();

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'languages_mapped' => $langsMapped,
            'languages_skipped' => $langsSkipped,
        ];
    }

    /**
     * Holt Übersetzungen für ein Snippet
     *
     * @return array<int, string>
     */
    private static function getSnippetTranslations(int $snippetId): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT clang_id, content FROM ' . \rex::getTable('snippets_translation') . ' WHERE snippet_id = ?',
            [$snippetId]
        );

        $translations = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $translations[(int) $sql->getValue('clang_id')] = (string) $sql->getValue('content');
            $sql->next();
        }

        return $translations;
    }

    /**
     * Speichert eine Übersetzung für ein Snippet
     */
    private static function saveSnippetTranslation(int $snippetId, int $clangId, string $content): void
    {
        $sql = \rex_sql::factory();

        // Prüfen ob Translation existiert
        $sql->setQuery(
            'SELECT id FROM ' . \rex::getTable('snippets_translation') . ' WHERE snippet_id = ? AND clang_id = ?',
            [$snippetId, $clangId]
        );

        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('snippets_translation'));

        if ($sql->getRows() > 0) {
            $sql->setWhere('snippet_id = :sid AND clang_id = :cid', ['sid' => $snippetId, 'cid' => $clangId]);
            $sql->setValue('content', $content);
            $sql->update();
        } else {
            $sql->setValue('snippet_id', $snippetId);
            $sql->setValue('clang_id', $clangId);
            $sql->setValue('content', $content);
            $sql->insert();
        }
    }

    /**
     * Validiert JSON-Struktur ohne zu importieren
     *
     * @return array{valid: bool, type?: string, count?: int, error?: string}
     */
    public static function validateJson(string $json): array
    {
        $data = json_decode($json, true);

        if (null === $data) {
            return ['valid' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }

        if (!isset($data['type'])) {
            return ['valid' => false, 'error' => 'Missing type field'];
        }

        $validTypes = [self::TYPE_SNIPPETS, self::TYPE_HTML_REPLACEMENTS, self::TYPE_ABBREVIATIONS, self::TYPE_TRANSLATIONS];
        if (!in_array($data['type'], $validTypes, true)) {
            return ['valid' => false, 'error' => 'Unknown type: ' . $data['type']];
        }

        if (!isset($data['items']) || !is_array($data['items'])) {
            return ['valid' => false, 'error' => 'Missing or invalid items array'];
        }

        return [
            'valid' => true,
            'type' => $data['type'],
            'count' => count($data['items']),
        ];
    }

    /**
     * Lädt alle Snippet-Kategorien
     *
     * @return array<int, array{name: string, icon: string}>
     */
    private static function loadCategories(): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT id, name, icon FROM ' . \rex::getTable('snippets_category') . ' ORDER BY name');

        $categories = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $categories[(int) $sql->getValue('id')] = [
                'name' => (string) $sql->getValue('name'),
                'icon' => (string) $sql->getValue('icon'),
            ];
            $sql->next();
        }

        return $categories;
    }

    /**
     * Erstellt eine Kategorie und gibt die ID zurück
     */
    private static function createCategory(string $name, string $icon = 'fa-folder'): int
    {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('snippets_category'));
        $sql->setValue('name', $name);
        $sql->setValue('icon', $icon);
        $sql->setValue('sort_order', 100);
        $sql->insert();

        return (int) $sql->getLastId();
    }
}
