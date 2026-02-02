<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Util\Parser;
use FriendsOfREDAXO\Snippets\Util\ContextDetector;
use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Domain\Snippet;

/**
 * Service für Snippet-Ersetzungen (Performance-optimiert)
 *
 * Nutzt Batch-Queries und strtr() für maximale Performance:
 * - Alle Snippets in einer DB-Query laden (statt n Queries)
 * - Alle Übersetzungen in einer DB-Query laden
 * - strtr() für schnellste Ersetzung
 *
 * @package redaxo\snippets
 */
class ReplacementService
{
    /** @var array<string, Snippet> Memory-Cache für geladene Snippets */
    private static array $snippetCache = [];

    /** @var array<string, string> Memory-Cache für Translations (key:clang => content) */
    private static array $translationCache = [];

    /**
     * Ersetzt alle Snippet-Platzhalter im Content (Performance-optimiert)
     *
     * @param array<string, mixed> $context
     */
    public static function replace(string $content, array $context = []): string
    {
        // Quick-Check: Enthält der Content überhaupt Snippets?
        if (!str_contains($content, '[[snippet:')) {
            return $content;
        }

        // Context-Check: Keine Ersetzung in Edit-Formularen!
        if (ContextDetector::isEditContext()) {
            return $content;
        }

        // Snippets finden
        $snippetMatches = Parser::findAll($content);

        if ([] === $snippetMatches) {
            return $content;
        }

        // Aktuellen Context ermitteln
        $currentContext = ContextDetector::isFrontend() ? 'frontend' : 'backend';
        $clangId = $context['clang_id'] ?? \rex_clang::getCurrentId();

        // OPTIMIERUNG: Alle Keys sammeln und in einer Query laden
        $keys = array_unique(array_column($snippetMatches, 'key'));
        $snippets = self::loadSnippetsBatch($keys);

        // OPTIMIERUNG: Alle Übersetzungen auf einmal laden
        $multilangSnippetIds = [];
        foreach ($snippets as $snippet) {
            if ($snippet->isMultilang()) {
                $multilangSnippetIds[] = $snippet->getId();
            }
        }
        $translations = self::loadTranslationsBatch($multilangSnippetIds, $clangId);

        // OPTIMIERUNG: Replacement-Map für strtr() bauen
        $replacements = [];

        foreach ($snippetMatches as $match) {
            $key = $match['key'];
            $params = $match['params'];
            $filters = $match['filters'];
            $fullMatch = $match['full_match'];

            // Snippet nicht gefunden oder nicht im Cache
            if (!isset($snippets[$key])) {
                $replacements[$fullMatch] = self::handleError($key, 'Snippet not found');
                continue;
            }

            $snippet = $snippets[$key];

            // Snippet inaktiv
            if (!$snippet->isActive()) {
                $replacements[$fullMatch] = '';
                continue;
            }

            // Context-Check
            $snippetContext = $snippet->getContext();
            if ('both' !== $snippetContext && $snippetContext !== $currentContext) {
                $replacements[$fullMatch] = '';
                continue;
            }

            try {
                // Content holen (mit Translation-Cache)
                $snippetContent = self::getContentCached($snippet, $clangId, $translations);

                if ('' === $snippetContent) {
                    $replacements[$fullMatch] = '';
                    continue;
                }

                // Rendern je nach Typ
                $rendered = match ($snippet->getContentType()) {
                    'php' => SnippetService::renderPhpDirect($snippet, $snippetContent, $params),
                    'html', 'text' => SnippetService::renderTemplateDirect($snippetContent, $params),
                    default => $snippetContent,
                };

                // Filter anwenden
                if ([] !== $filters) {
                    $rendered = FilterService::apply($rendered, $filters);
                }

                $replacements[$fullMatch] = $rendered;
            } catch (\Exception $e) {
                $replacements[$fullMatch] = self::handleError($key, $e->getMessage());
                \rex_logger::logException($e);
            }
        }

        // OPTIMIERUNG: strtr() ist schneller als mehrere str_replace()
        return strtr($content, $replacements);
    }

    /**
     * Lädt mehrere Snippets in einer Query und cached sie
     *
     * @param array<string> $keys
     * @return array<string, Snippet>
     */
    private static function loadSnippetsBatch(array $keys): array
    {
        // Prüfen welche Keys noch nicht im Cache sind
        $missingKeys = [];
        foreach ($keys as $key) {
            if (!isset(self::$snippetCache[$key])) {
                $missingKeys[] = $key;
            }
        }

        // Fehlende Keys aus DB laden
        if ([] !== $missingKeys) {
            $loaded = SnippetRepository::findByKeys($missingKeys);
            foreach ($loaded as $key => $snippet) {
                self::$snippetCache[$key] = $snippet;
            }
        }

        // Ergebnis aus Cache zusammenstellen
        $result = [];
        foreach ($keys as $key) {
            if (isset(self::$snippetCache[$key])) {
                $result[$key] = self::$snippetCache[$key];
            }
        }

        return $result;
    }

    /**
     * Lädt Übersetzungen für mehrere Snippets in einer Query
     *
     * @param array<int> $snippetIds
     * @return array<int, string>
     */
    private static function loadTranslationsBatch(array $snippetIds, int $clangId): array
    {
        if ([] === $snippetIds) {
            return [];
        }

        // Prüfen welche IDs noch nicht im Cache sind
        $missingIds = [];
        foreach ($snippetIds as $id) {
            $cacheKey = $id . ':' . $clangId;
            if (!isset(self::$translationCache[$cacheKey])) {
                $missingIds[] = $id;
            }
        }

        // Fehlende aus DB laden
        if ([] !== $missingIds) {
            $loaded = SnippetRepository::findTranslationsByIds($missingIds, $clangId);
            foreach ($loaded as $id => $content) {
                self::$translationCache[$id . ':' . $clangId] = $content;
            }
            // Auch nicht gefundene markieren (null = kein Translation vorhanden)
            foreach ($missingIds as $id) {
                if (!isset($loaded[$id])) {
                    self::$translationCache[$id . ':' . $clangId] = '';
                }
            }
        }

        // Ergebnis aus Cache zusammenstellen
        $result = [];
        foreach ($snippetIds as $id) {
            $cacheKey = $id . ':' . $clangId;
            if (isset(self::$translationCache[$cacheKey]) && '' !== self::$translationCache[$cacheKey]) {
                $result[$id] = self::$translationCache[$cacheKey];
            }
        }

        return $result;
    }

    /**
     * Holt Content mit Cache-Nutzung
     *
     * @param array<int, string> $translations
     */
    private static function getContentCached(Snippet $snippet, int $clangId, array $translations): string
    {
        if (!$snippet->isMultilang()) {
            return $snippet->getContent();
        }

        // Translation aus vorgeladenem Array nutzen
        if (isset($translations[$snippet->getId()])) {
            return $translations[$snippet->getId()];
        }

        // Fallback auf Standard-Content
        return $snippet->getContent();
    }

    /**
     * Fehler-Handling
     */
    private static function handleError(string $key, string $message): string
    {
        if (\rex::isDebugMode()) {
            return '<!-- Snippet Error: ' . \rex_escape($key) . ' - ' . \rex_escape($message) . ' -->';
        }
        return '';
    }

    /**
     * Cache leeren (z.B. nach Snippet-Änderung)
     */
    public static function clearCache(): void
    {
        self::$snippetCache = [];
        self::$translationCache = [];
    }

    /**
     * Rendert ein einzelnes Snippet (für DOM-Manipulation)
     */
    public static function renderSnippet(Snippet $snippet, string $context, int $clangId): string
    {
        return SnippetService::render(
            $snippet->getKey(),
            [],
            $clangId,
            $context
        );
    }
}
