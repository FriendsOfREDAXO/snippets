<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Domain\Snippet;
use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;

/**
 * Stellt Snippets und String-Übersetzungen für Editor-Integrationen (CKEditor 5) bereit.
 *
 * Liefert nur, was der aktuelle Backend-User sehen darf:
 * - Snippets: nur aktive, nicht reine Backend-Snippets; PHP-Snippets nur für Admins
 * - Übersetzungen: nur aktive Strings, Vorschau in der angefragten Sprache (mit Fallback)
 *
 * Zusätzlich wird ein Index aller bekannten Keys geliefert (nur Keys, Titel, Status),
 * damit der Editor vorhandene Platzhalter erkennen und unbekannte markieren kann.
 *
 * @package redaxo\snippets
 */
class EditorItemsService
{
    public const TYPE_SNIPPETS = 'snippets';
    public const TYPE_TRANSLATIONS = 'translations';

    private const PREVIEW_LENGTH = 140;

    /**
     * Darf der aktuelle User den Editor-Picker überhaupt nutzen?
     */
    public static function canUse(): bool
    {
        return PermissionService::canView() || PermissionService::canTranslate();
    }

    /**
     * @param list<string> $types erlaubte Typen (snippets, translations); leer = alle
     * @param list<string> $categories Kategorie-IDs oder -Namen; leer = alle
     * @return array<string, mixed>
     */
    public static function build(int $clangId, array $types = [], array $categories = []): array
    {
        $types = self::normalizeTypes($types);
        $categoryMap = self::loadCategories();
        $categoryFilter = self::resolveCategoryFilter($categories, $categoryMap);

        $withSnippets = in_array(self::TYPE_SNIPPETS, $types, true) && PermissionService::canView();
        $withTranslations = in_array(self::TYPE_TRANSLATIONS, $types, true) && self::canUse();

        $result = [
            'clang_id' => $clangId,
            'types' => array_values(array_filter([
                $withSnippets ? self::TYPE_SNIPPETS : null,
                $withTranslations ? self::TYPE_TRANSLATIONS : null,
            ])),
            'categories' => array_values(array_map(
                static fn (array $category): array => ['id' => $category['id'], 'name' => $category['name']],
                $categoryMap,
            )),
            'snippets' => [],
            'translations' => [],
            'known' => ['snippets' => [], 'translations' => []],
            'permissions' => [
                'edit' => PermissionService::canEdit(),
                'translate' => PermissionService::canTranslate(),
            ],
            'urls' => [
                'snippets' => PermissionService::canView() ? \rex_url::backendPage('snippets/overview', [], false) : '',
                'translations' => PermissionService::canTranslate() ? \rex_url::backendPage('snippets/translations', [], false) : '',
            ],
        ];

        if ($withSnippets) {
            [$result['snippets'], $result['known']['snippets']] = self::buildSnippets($clangId, $categoryMap, $categoryFilter);
        }

        if ($withTranslations) {
            [$result['translations'], $result['known']['translations']] = self::buildTranslations($clangId, $categoryMap, $categoryFilter);
        }

        return $result;
    }

    /**
     * Ermittelt Parameter-Namen eines Snippets.
     *
     * - HTML/Text: Platzhalter {name} (siehe SnippetService::renderTemplate)
     * - PHP: Zugriffe auf $SNIPPET_PARAMS['name'] (optional mit ?? 'Default')
     *
     * @return list<array{name: string, default: string}>
     */
    public static function detectParams(string $content, string $contentType): array
    {
        $params = [];

        if ('php' === $contentType) {
            $pattern = '/\$SNIPPET_PARAMS\s*\[\s*([\'"])([A-Za-z_][\w\-]*)\1\s*\](?:\s*\?\?\s*([\'"])(.*?)\3)?/';
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $name = $match[2];
                    if (!isset($params[$name]) || ('' === $params[$name] && isset($match[4]))) {
                        $params[$name] = $match[4] ?? '';
                    }
                }
            }
        } else {
            // {name}, aber nicht {{ name }} (Sprog-Syntax) und keine CSS-/JSON-Blöcke
            $pattern = '/(?<!\{)\{([A-Za-z_][\w\-]*)\}(?!\})/';
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $name) {
                    $params[$name] ??= '';
                }
            }
        }

        $result = [];
        foreach ($params as $name => $default) {
            $result[] = ['name' => (string) $name, 'default' => (string) $default];
        }

        return $result;
    }

    /**
     * Erzeugt eine kurze, einzeilige Textvorschau.
     */
    public static function createPreview(string $content, string $contentType): string
    {
        if ('php' === $contentType) {
            return '';
        }

        $withoutCode = (string) preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#is', ' ', $content);
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($withoutCode), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Reines Markup ohne Text (z. B. Web-Components): erstes Tag als Hinweis zeigen
        if ('' === $text && preg_match('/<[a-z][^>]*>/i', $content, $match)) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $match[0]));
        }

        if (mb_strlen($text) > self::PREVIEW_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::PREVIEW_LENGTH - 1)) . '…';
        }

        return $text;
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private static function normalizeTypes(array $types): array
    {
        $allowed = [self::TYPE_SNIPPETS, self::TYPE_TRANSLATIONS];
        $types = array_values(array_intersect($allowed, array_map(static fn ($type): string => strtolower(trim((string) $type)), $types)));

        return [] === $types ? $allowed : $types;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private static function loadCategories(): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT id, name FROM ' . \rex::getTable('snippets_category') . ' ORDER BY sort_order, name');

        $categories = [];
        foreach ($sql as $row) {
            $id = (int) $row->getValue('id');
            $categories[$id] = ['id' => $id, 'name' => (string) $row->getValue('name')];
        }

        return $categories;
    }

    /**
     * Wandelt Kategorie-Angaben (IDs oder Namen) in eine Liste von IDs um.
     *
     * @param list<string> $categories
     * @param array<int, array{id: int, name: string}> $categoryMap
     * @return list<int>|null null = kein Filter
     */
    private static function resolveCategoryFilter(array $categories, array $categoryMap): ?array
    {
        $categories = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $categories), static fn (string $value): bool => '' !== $value));
        if ([] === $categories) {
            return null;
        }

        $ids = [];
        foreach ($categories as $category) {
            if (ctype_digit($category) && isset($categoryMap[(int) $category])) {
                $ids[] = (int) $category;
                continue;
            }
            foreach ($categoryMap as $id => $data) {
                if (0 === strcasecmp($data['name'], $category)) {
                    $ids[] = $id;
                }
            }
        }

        // Angegebene, aber unbekannte Kategorien → bewusst leere Auswahl statt "alles"
        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array{id: int, name: string}> $categoryMap
     * @param list<int>|null $categoryFilter
     * @return array{0: list<array<string, mixed>>, 1: array<string, array{title: string, active: bool}>}
     */
    private static function buildSnippets(int $clangId, array $categoryMap, ?array $categoryFilter): array
    {
        $isAdmin = PermissionService::canEditPhp();
        $all = SnippetRepository::findAll();

        $known = [];
        $candidates = [];
        foreach ($all as $snippet) {
            $known[$snippet->getKey()] = ['title' => $snippet->getTitle(), 'active' => $snippet->isActive()];

            if (!$snippet->isActive() || 'backend' === $snippet->getContext()) {
                continue;
            }
            if ('php' === $snippet->getContentType() && !$isAdmin) {
                continue;
            }
            if (null !== $categoryFilter && !in_array((int) $snippet->getCategoryId(), $categoryFilter, true)) {
                continue;
            }
            $candidates[] = $snippet;
        }

        $multilangIds = array_map(
            static fn (Snippet $snippet): int => $snippet->getId(),
            array_filter($candidates, static fn (Snippet $snippet): bool => $snippet->isMultilang()),
        );
        $translations = SnippetRepository::findTranslationsByIds(array_values($multilangIds), $clangId);

        $items = [];
        foreach ($candidates as $snippet) {
            $content = $snippet->getContent();
            if ($snippet->isMultilang() && isset($translations[$snippet->getId()]) && '' !== $translations[$snippet->getId()]) {
                $content = $translations[$snippet->getId()];
            }

            $categoryId = (int) $snippet->getCategoryId();
            $items[] = [
                'key' => $snippet->getKey(),
                'title' => $snippet->getTitle(),
                'description' => (string) $snippet->getDescription(),
                'type' => $snippet->getContentType(),
                'category_id' => isset($categoryMap[$categoryId]) ? $categoryId : 0,
                'preview' => self::createPreview($content, $snippet->getContentType()),
                'params' => self::detectParams($content, $snippet->getContentType()),
            ];
        }

        return [$items, $known];
    }

    /**
     * @param array<int, array{id: int, name: string}> $categoryMap
     * @param list<int>|null $categoryFilter
     * @return array{0: list<array<string, mixed>>, 1: array<string, array{title: string, active: bool}>}
     */
    private static function buildTranslations(int $clangId, array $categoryMap, ?array $categoryFilter): array
    {
        $fallbackClangId = null;
        $addon = \rex_addon::get('snippets');
        if ((bool) $addon->getConfig('tstr_fallback_enabled', false)) {
            $fallbackClangId = (int) $addon->getConfig('tstr_fallback_clang_id', \rex_clang::getStartId());
        }

        $known = [];
        $items = [];
        foreach (TranslationStringRepository::findAll() as $string) {
            $value = $string->getValue($clangId);
            $known[$string->getKey()] = ['title' => self::createPreview($value, 'text'), 'active' => $string->isActive()];

            if (!$string->isActive()) {
                continue;
            }

            $categoryId = (int) $string->getCategoryId();
            if (null !== $categoryFilter && !in_array($categoryId, $categoryFilter, true)) {
                continue;
            }

            $missing = '' === $value;
            if ($missing && null !== $fallbackClangId && $fallbackClangId !== $clangId) {
                $value = $string->getValue($fallbackClangId);
            }

            $items[] = [
                'key' => $string->getKey(),
                'category_id' => isset($categoryMap[$categoryId]) ? $categoryId : 0,
                'preview' => self::createPreview($value, 'text'),
                'missing' => $missing,
            ];
        }

        return [$items, $known];
    }
}
