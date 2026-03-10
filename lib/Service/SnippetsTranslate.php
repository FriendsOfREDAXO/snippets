<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;

/**
 * Service für String-Übersetzungen und Frontend-Replacement
 *
 * Ersetzt [[ key ]]-Platzhalter im Output (Standard).
 * Optional auch {{ key }}-Syntax (Sprog-Kompatibilität).
 *
 * Performance-Architektur (PHP 8.4+):
 * - Eine Batch-SQL-Query pro Sprache+Request (gecacht)
 * - Replacement-Map wird nur einmal pro Sprache aufgebaut und gecacht
 * - strtr() mit assoziativem Array: schnellste PHP-Methode für Multi-Replacement
 * - Config-Werte werden einmalig geladen und gecacht
 * - Rekursive Verschachtelung mit Early-Exit wenn keine Platzhalter mehr vorhanden
 * - Kein Regex im Hot Path – nur str_contains() als Guard
 *
 * @package redaxo\snippets
 */
class SnippetsTranslate
{
    /** Maximale Verschachtelungstiefe für rekursive Snippet-Auflösung */
    private const MAX_DEPTH = 5;

    /** @var array<string, string>|null Cache für aktive Strings (key => value) */
    private static ?array $cache = null;

    /** @var array<string, string>|null Gecachte Replacement-Map ([[ key ]] => value, [[key]] => value, …) */
    private static ?array $replacementMap = null;

    /** @var int|null Sprach-ID für die der Cache gilt */
    private static ?int $cachedClangId = null;

    /** @var bool|null Gecachter Sprog-Syntax-Config-Wert */
    private static ?bool $sprogSyntaxEnabled = null;

    /** @var bool Ob einer der Werte im Cache selbst Platzhalter enthält */
    private static bool $hasNestedPlaceholders = false;

    /**
     * Gibt den übersetzten Wert eines einzelnen Keys zurück.
     *
     * Nutzbar in Modulen, Templates und PHP-Code ohne OUTPUT_FILTER.
     * Verschachtelte Snippets im Wert werden automatisch aufgelöst.
     *
     * Beispiele:
     *   SnippetsTranslate::get('nav.home')
     *   SnippetsTranslate::get('nav.home', 2)          // bestimmte Sprache
     *   SnippetsTranslate::get('nav.home', null, 'Startseite')  // mit Fallback
     *   // Wenn "footer" = "© [[ company ]]" und "company" = "ACME"
     *   // → SnippetsTranslate::get('footer') liefert "© ACME"
     *
     * @param string $key Übersetzungsschlüssel (z.B. "nav.home")
     * @param int|null $clangId Sprach-ID, null = aktuelle Sprache
     * @param string $default Fallback-Wert wenn Key nicht gefunden
     */
    public static function get(string $key, ?int $clangId = null, string $default = ''): string
    {
        $clangId ??= \rex_clang::getCurrentId();
        self::ensureCache($clangId);

        $value = self::$cache[$key] ?? $default;

        // Verschachtelte Platzhalter nur prüfen wenn es überhaupt welche gibt
        if ('' !== $value && self::$hasNestedPlaceholders && str_contains($value, '[[')) {
            $value = self::replace($value, $clangId);
        }

        return $value;
    }

    /**
     * Ersetzt alle [[ key ]]-Platzhalter im Content.
     * Bei aktivierter Sprog-Syntax werden auch {{ key }}-Platzhalter ersetzt.
     *
     * Verschachtelte Snippets werden rekursiv aufgelöst (max. 5 Ebenen).
     * Beispiel: Wenn Snippet "footer.text" den Wert "© [[ company.name ]]" hat,
     * wird auch [[ company.name ]] im Ergebnis ersetzt.
     */
    public static function replace(string $content, int $clangId): string
    {
        $sprogSyntax = self::isSprogSyntaxEnabled();

        // Quick-Check: Enthält der Content überhaupt Platzhalter?
        $hasDefault = str_contains($content, '[[');
        $hasSprog = $sprogSyntax && str_contains($content, '{{');

        if (!$hasDefault && !$hasSprog) {
            return $content;
        }

        // Cache + Replacement-Map sicherstellen
        self::ensureCache($clangId);

        if (null === self::$replacementMap || [] === self::$replacementMap) {
            return $content;
        }

        // strtr() mit gecachter Map – ein Durchlauf durch den Content
        $content = strtr($content, self::$replacementMap);

        // Rekursion nur wenn Werte selbst Platzhalter enthalten
        if (self::$hasNestedPlaceholders) {
            $depth = 1;
            while ($depth < self::MAX_DEPTH) {
                $hasMore = str_contains($content, '[[');
                if (!$hasMore && $sprogSyntax) {
                    $hasMore = str_contains($content, '{{');
                }
                if (!$hasMore) {
                    break;
                }
                $content = strtr($content, self::$replacementMap);
                ++$depth;
            }
        }

        return $content;
    }

    /**
     * Gibt alle aktiven Strings für eine Sprache zurück (cached)
     *
     * @return array<string, string> key => value
     */
    public static function getStringsForClang(int $clangId): array
    {
        self::ensureCache($clangId);
        return self::$cache ?? [];
    }

    /**
     * Cache zurücksetzen (nach Änderungen an Übersetzungen)
     */
    public static function clearCache(): void
    {
        self::$cache = null;
        self::$replacementMap = null;
        self::$cachedClangId = null;
        self::$sprogSyntaxEnabled = null;
        self::$hasNestedPlaceholders = false;
    }

    /**
     * Stellt sicher, dass Cache und Replacement-Map für die Sprache geladen sind.
     *
     * Baut die Replacement-Map nur einmal auf und erkennt dabei,
     * ob Werte selbst Platzhalter enthalten (für Verschachtelungs-Optimierung).
     */
    private static function ensureCache(int $clangId): void
    {
        if (null !== self::$cache && self::$cachedClangId === $clangId) {
            return;
        }

        // Strings aus DB laden (eine Query)
        self::$cache = TranslationStringRepository::findAllActiveForClang($clangId);
        self::$cachedClangId = $clangId;

        if ([] === self::$cache) {
            self::$replacementMap = [];
            self::$hasNestedPlaceholders = false;
            return;
        }

        // Replacement-Map aufbauen und Verschachtelung erkennen
        $sprogSyntax = self::isSprogSyntaxEnabled();
        $map = [];
        $hasNested = false;

        foreach (self::$cache as $key => $value) {
            // Standard-Syntax: [[ key ]] und [[key]]
            $map['[[ ' . $key . ' ]]'] = $value;
            $map['[[' . $key . ']]'] = $value;

            // Sprog-Syntax: {{ key }} und {{key}}
            if ($sprogSyntax) {
                $map['{{ ' . $key . ' }}'] = $value;
                $map['{{' . $key . '}}'] = $value;
            }

            // Erkennen ob IRGENDEIN Wert selbst Platzhalter enthält
            if (!$hasNested && str_contains($value, '[[')) {
                $hasNested = true;
            }
            if (!$hasNested && $sprogSyntax && str_contains($value, '{{')) {
                $hasNested = true;
            }
        }

        self::$replacementMap = $map;
        self::$hasNestedPlaceholders = $hasNested;
    }

    /**
     * Gibt gecacht zurück ob Sprog-Syntax aktiviert ist.
     * Liest die Config nur einmal pro Request.
     */
    private static function isSprogSyntaxEnabled(): bool
    {
        return self::$sprogSyntaxEnabled ??= (bool) \rex_addon::get('snippets')->getConfig('tstr_sprog_syntax', false);
    }

    /**
     * Mapping von REDAXO-Sprachcode zu DeepL-Sprachcode
     *
     * REDAXO verwendet Codes wie 'de', 'en', 'fr', 'de_de', 'en_gb'
     * DeepL verwendet Codes wie 'DE', 'EN-GB', 'FR'
     */
    public static function getDeeplLanguageCode(string $rexClangCode): string
    {
        // Konfiguriertes Mapping prüfen (gecacht)
        static $customMap = null;
        if (null === $customMap) {
            $customMapping = (string) \rex_addon::get('snippets')->getConfig('deepl_language_mapping', '');
            $customMap = '' !== $customMapping ? self::parseLanguageMapping($customMapping) : [];
        }

        $normalizedCode = strtolower(trim($rexClangCode));
        if (isset($customMap[$normalizedCode])) {
            return strtoupper($customMap[$normalizedCode]);
        }

        // Automatisches Standard-Mapping
        return self::DEEPL_MAP[$normalizedCode]
            ?? self::buildDeeplFallback($normalizedCode);
    }

    /**
     * Konstante Mapping-Tabelle: REDAXO-Code → DeepL-Code
     * Als Klassenkonstante: wird beim Kompilieren in OPcache eingebettet,
     * kein Array-Aufbau zur Laufzeit.
     */
    private const DEEPL_MAP = [
        'de' => 'DE',
        'de_de' => 'DE',
        'en' => 'EN-GB',
        'en_gb' => 'EN-GB',
        'en_us' => 'EN-US',
        'fr' => 'FR',
        'fr_fr' => 'FR',
        'es' => 'ES',
        'es_es' => 'ES',
        'it' => 'IT',
        'it_it' => 'IT',
        'nl' => 'NL',
        'nl_nl' => 'NL',
        'pl' => 'PL',
        'pl_pl' => 'PL',
        'pt' => 'PT-PT',
        'pt_pt' => 'PT-PT',
        'pt_br' => 'PT-BR',
        'ru' => 'RU',
        'ru_ru' => 'RU',
        'ja' => 'JA',
        'ja_jp' => 'JA',
        'zh' => 'ZH-HANS',
        'zh_cn' => 'ZH-HANS',
        'zh_tw' => 'ZH-HANT',
        'ko' => 'KO',
        'ko_kr' => 'KO',
        'da' => 'DA',
        'da_dk' => 'DA',
        'fi' => 'FI',
        'fi_fi' => 'FI',
        'sv' => 'SV',
        'sv_se' => 'SV',
        'nb' => 'NB',
        'nb_no' => 'NB',
        'el' => 'EL',
        'el_gr' => 'EL',
        'cs' => 'CS',
        'cs_cz' => 'CS',
        'sk' => 'SK',
        'sk_sk' => 'SK',
        'hu' => 'HU',
        'hu_hu' => 'HU',
        'ro' => 'RO',
        'ro_ro' => 'RO',
        'bg' => 'BG',
        'bg_bg' => 'BG',
        'tr' => 'TR',
        'tr_tr' => 'TR',
        'uk' => 'UK',
        'uk_ua' => 'UK',
        'et' => 'ET',
        'et_ee' => 'ET',
        'lv' => 'LV',
        'lv_lv' => 'LV',
        'lt' => 'LT',
        'lt_lt' => 'LT',
        'sl' => 'SL',
        'sl_si' => 'SL',
    ];

    /**
     * Fallback für unbekannte Sprach-Codes
     */
    private static function buildDeeplFallback(string $code): string
    {
        // Fallback: Ersten Teil des Codes nehmen und uppercasen
        $parts = preg_split('/[_-]/', $code);
        if (null !== $parts && count($parts) >= 2) {
            return strtoupper($parts[0]) . '-' . strtoupper($parts[1]);
        }

        return strtoupper($code);
    }

    /**
     * Parst benutzerdefiniertes Sprachmapping aus der Konfiguration
     *
     * Format: "de_de=DE\nen_gb=EN-GB\nfr_fr=FR"
     *
     * @return array<string, string>
     */
    private static function parseLanguageMapping(string $config): array
    {
        $map = [];
        $lines = explode("\n", $config);
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || !str_contains($line, '=')) {
                continue;
            }
            [$rexCode, $deeplCode] = explode('=', $line, 2);
            $map[strtolower(trim($rexCode))] = trim($deeplCode);
        }

        return $map;
    }

    /**
     * Übersetzt einen Text via DeepL (nutzt writeassist-Token)
     *
     * @throws \Exception
     */
    public static function translateWithDeepL(string $text, string $targetClangCode, ?string $sourceClangCode = null): string
    {
        if (!self::isDeeplAvailable()) {
            throw new \rex_exception('DeepL API nicht verfügbar. Bitte writeassist-Addon installieren und API-Key konfigurieren.');
        }

        $deepl = new \FriendsOfREDAXO\WriteAssist\DeeplApi();

        $targetLang = self::getDeeplLanguageCode($targetClangCode);
        $sourceLang = null !== $sourceClangCode ? self::getDeeplLanguageCode($sourceClangCode) : null;

        $result = $deepl->translate($text, $targetLang, $sourceLang, false);

        return $result['text'] ?? '';
    }

    /**
     * Prüft ob DeepL über writeassist verfügbar ist
     */
    public static function isDeeplAvailable(): bool
    {
        if (!\rex_addon::get('writeassist')->isAvailable()) {
            return false;
        }

        $apiKey = (string) \rex_addon::get('writeassist')->getConfig('api_key', '');
        return '' !== $apiKey;
    }
}
