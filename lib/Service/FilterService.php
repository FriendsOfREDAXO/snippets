<?php

namespace FriendsOfREDAXO\Snippets\Service;

/**
 * Filter-Service für Snippet-Ausgaben
 *
 * Filter werden nach dem Snippet-Key mit | notiert:
 * [[snippet:key|upper|limit(100,...)]]
 *
 * Verfügbare Filter:
 * - upper: Großbuchstaben
 * - lower: Kleinbuchstaben
 * - title: Title Case (Jedes Wort Groß)
 * - capitalize: Erster Buchstabe groß
 * - trim: Whitespace entfernen
 * - truncate(n, suffix, break_words): Auf n Zeichen begrenzen (nutzt rex_formatter::truncate)
 * - limit(n, suffix): Alias für truncate
 * - words(n, suffix): Auf n Wörter begrenzen
 * - nl2br: Zeilenumbrüche zu <br> konvertieren
 * - raw: Keine nl2br Konvertierung (Standard ist nl2br)
 * - markdown: Markdown zu HTML parsen
 * - strip_tags(allowed): HTML-Tags entfernen
 * - escape: HTML-Entities escapen
 * - sanitize: HTML sicher machen (nutzt rex_string::sanitizeHtml)
 * - format(args...): sprintf-Formatierung (nutzt rex_formatter::sprintf)
 * - default(value): Standardwert wenn leer
 * - replace(search, replace): Text ersetzen
 * - prefix(text): Text voranstellen
 * - suffix(text): Text anhängen
 * - wrap(before, after): Text umschließen
 * - date(format): Datum formatieren (nutzt rex_formatter::date)
 * - intldate(format): Internationales Datum (nutzt rex_formatter::intlDate)
 * - number(decimals, dec_point, thousands_sep): Zahl formatieren (nutzt rex_formatter::number)
 * - bytes(precision): Bytes formatieren (nutzt rex_formatter::bytes)
 * - slug: URL-freundlichen Slug erstellen (nutzt rex_string::normalize)
 * - url: Als klickbaren Link ausgeben (nutzt rex_formatter::url)
 * - email: Als klickbare E-Mail ausgeben (nutzt rex_formatter::email)
 * - widont: Verhindert einzelne Wörter am Zeilenende (nutzt rex_formatter::widont)
 * - highlight: Syntax-Highlighting (nutzt rex_string::highlight)
 * - json: Als JSON ausgeben
 * - base64: Base64-Kodierung
 * - reverse: Text umkehren
 * - wordwrap(width, break): Zeilenumbruch einfügen
 *
 * @package redaxo\snippets
 */
class FilterService
{
    /**
     * Wendet Filter auf den Inhalt an
     *
     * @param array<int, array{name: string, args: array<int, string>}> $filters
     */
    public static function apply(string $content, array $filters): string
    {
        foreach ($filters as $filter) {
            $name = $filter['name'];
            $args = $filter['args'];

            $content = match ($name) {
                'upper' => self::upper($content),
                'lower' => self::lower($content),
                'title' => self::title($content),
                'capitalize' => self::capitalize($content),
                'trim' => self::trim($content),
                'truncate', 'limit' => self::truncate($content, $args),
                'words' => self::words($content, $args),
                'nl2br' => self::nl2br($content),
                'raw' => $content, // Nichts tun - raw verhindert auto-nl2br
                'markdown' => self::markdown($content),
                'strip_tags' => self::stripTags($content, $args),
                'escape' => self::escape($content),
                'sanitize' => self::sanitize($content),
                'format' => self::format($content, $args),
                'default' => self::defaultValue($content, $args),
                'replace' => self::replace($content, $args),
                'prefix' => self::prefix($content, $args),
                'suffix' => self::suffix($content, $args),
                'wrap' => self::wrap($content, $args),
                'date' => self::date($content, $args),
                'intldate' => self::intlDate($content, $args),
                'number' => self::number($content, $args),
                'bytes' => self::bytes($content, $args),
                'slug' => self::slug($content),
                'url' => self::url($content, $args),
                'email' => self::email($content, $args),
                'widont' => self::widont($content),
                'highlight' => self::highlight($content),
                'json' => self::json($content),
                'base64' => self::base64($content),
                'reverse' => self::reverse($content),
                'wordwrap' => self::wordwrap($content, $args),
                default => $content, // Unbekannter Filter: ignorieren
            };
        }

        return $content;
    }

    /**
     * Prüft ob ein Filter existiert
     */
    public static function exists(string $name): bool
    {
        return in_array($name, self::getAvailableFilters(), true);
    }

    /**
     * Gibt alle verfügbaren Filter zurück
     *
     * @return array<int, string>
     */
    public static function getAvailableFilters(): array
    {
        return [
            'upper',
            'lower',
            'title',
            'capitalize',
            'trim',
            'truncate',
            'limit', // Alias für truncate
            'words',
            'nl2br',
            'raw',
            'markdown',
            'strip_tags',
            'escape',
            'sanitize',
            'format',
            'default',
            'replace',
            'prefix',
            'suffix',
            'wrap',
            'date',
            'intldate',
            'number',
            'bytes',
            'slug',
            'url',
            'email',
            'widont',
            'highlight',
            'json',
            'base64',
            'reverse',
            'wordwrap',
        ];
    }

    // ========================================
    // Filter-Implementierungen
    // ========================================

    /**
     * Konvertiert zu Großbuchstaben
     */
    private static function upper(string $content): string
    {
        return mb_strtoupper($content, 'UTF-8');
    }

    /**
     * Konvertiert zu Kleinbuchstaben
     */
    private static function lower(string $content): string
    {
        return mb_strtolower($content, 'UTF-8');
    }

    /**
     * Title Case: Jedes Wort beginnt mit Großbuchstaben
     */
    private static function title(string $content): string
    {
        return mb_convert_case($content, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Erster Buchstabe groß
     */
    private static function capitalize(string $content): string
    {
        if ('' === $content) {
            return $content;
        }

        $firstChar = mb_substr($content, 0, 1, 'UTF-8');
        $rest = mb_substr($content, 1, null, 'UTF-8');

        return mb_strtoupper($firstChar, 'UTF-8') . $rest;
    }

    /**
     * Whitespace am Anfang und Ende entfernen
     */
    private static function trim(string $content): string
    {
        return trim($content);
    }

    /**
     * Begrenzt auf n Zeichen (nutzt rex_formatter::truncate)
     *
     * @param array<int, string> $args [0] = length, [1] = etc (default: '…'), [2] = break_words
     */
    private static function truncate(string $content, array $args): string
    {
        $format = [
            'length' => (int) ($args[0] ?? 80),
            'etc' => $args[1] ?? '…',
            'break_words' => (bool) ($args[2] ?? false),
        ];

        return \rex_formatter::truncate($content, $format);
    }

    /**
     * Begrenzt auf n Wörter
     *
     * @param array<int, string> $args [0] = word count, [1] = suffix (default: '...')
     */
    private static function words(string $content, array $args): string
    {
        $wordCount = (int) ($args[0] ?? 20);
        $suffix = $args[1] ?? '...';

        $words = preg_split('/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        if (!$words || count($words) <= $wordCount) {
            return $content;
        }

        return implode(' ', array_slice($words, 0, $wordCount)) . $suffix;
    }

    /**
     * Zeilenumbrüche zu <br> konvertieren (nutzt rex_formatter::nl2br)
     */
    private static function nl2br(string $content): string
    {
        return \rex_formatter::nl2br($content);
    }

    /**
     * Markdown zu HTML parsen
     */
    private static function markdown(string $content): string
    {
        // Prüfen ob MarkItUp oder Parsedown verfügbar
        if (class_exists(\Parsedown::class)) {
            $parsedown = new \Parsedown();
            $parsedown->setSafeMode(true);
            return $parsedown->text($content);
        }

        // Fallback: rex_markdown (REDAXO Core)
        if (class_exists(\rex_markdown::class)) {
            return \rex_markdown::factory()->parse($content);
        }

        // Minimal-Markdown-Konvertierung
        return self::minimalMarkdown($content);
    }

    /**
     * Minimale Markdown-Konvertierung als Fallback
     */
    private static function minimalMarkdown(string $content): string
    {
        // Bold
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content) ?? $content;
        $content = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $content) ?? $content;

        // Italic
        $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content) ?? $content;
        $content = preg_replace('/_(.+?)_/', '<em>$1</em>', $content) ?? $content;

        // Code
        $content = preg_replace('/`(.+?)`/', '<code>$1</code>', $content) ?? $content;

        // Links
        $content = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $content) ?? $content;

        // Paragraphs
        $content = '<p>' . preg_replace('/\n\n+/', '</p><p>', trim($content)) . '</p>';

        return $content;
    }

    /**
     * HTML-Tags entfernen
     *
     * @param array<int, string> $args [0] = allowed tags (z.B. '<p><br><strong>')
     */
    private static function stripTags(string $content, array $args): string
    {
        $allowedTags = $args[0] ?? '';
        return strip_tags($content, $allowedTags);
    }

    /**
     * HTML-Entities escapen
     */
    private static function escape(string $content): string
    {
        return \rex_escape($content);
    }

    /**
     * HTML sicher machen (nutzt rex_string::sanitizeHtml)
     */
    private static function sanitize(string $content): string
    {
        return \rex_string::sanitizeHtml($content);
    }

    /**
     * sprintf-Formatierung (nutzt rex_formatter::sprintf)
     *
     * @param array<int, string> $args Format-Argumente
     */
    private static function format(string $content, array $args): string
    {
        if ([] === $args) {
            return $content;
        }

        // Wenn nur ein Argument: als Format-String verwenden
        if (1 === count($args)) {
            return \rex_formatter::sprintf($content, $args[0]);
        }

        // Mehrere Argumente: sprintf direkt
        try {
            return sprintf($content, ...$args);
        } catch (\Throwable $e) {
            return $content;
        }
    }

    /**
     * Standardwert wenn leer
     *
     * @param array<int, string> $args [0] = default value
     */
    private static function defaultValue(string $content, array $args): string
    {
        if ('' === trim($content)) {
            return $args[0] ?? '';
        }

        return $content;
    }

    /**
     * Text ersetzen
     *
     * @param array<int, string> $args [0] = search, [1] = replace
     */
    private static function replace(string $content, array $args): string
    {
        $search = $args[0] ?? '';
        $replace = $args[1] ?? '';

        if ('' === $search) {
            return $content;
        }

        return str_replace($search, $replace, $content);
    }

    /**
     * Text voranstellen
     *
     * @param array<int, string> $args [0] = prefix text
     */
    private static function prefix(string $content, array $args): string
    {
        $prefix = $args[0] ?? '';

        // Nur wenn Content nicht leer
        if ('' === trim($content)) {
            return $content;
        }

        return $prefix . $content;
    }

    /**
     * Text anhängen
     *
     * @param array<int, string> $args [0] = suffix text
     */
    private static function suffix(string $content, array $args): string
    {
        $suffix = $args[0] ?? '';

        // Nur wenn Content nicht leer
        if ('' === trim($content)) {
            return $content;
        }

        return $content . $suffix;
    }

    /**
     * Text umschließen
     *
     * @param array<int, string> $args [0] = before, [1] = after
     */
    private static function wrap(string $content, array $args): string
    {
        $before = $args[0] ?? '';
        $after = $args[1] ?? $before;

        // Nur wenn Content nicht leer
        if ('' === trim($content)) {
            return $content;
        }

        return $before . $content . $after;
    }

    /**
     * Datum formatieren (nutzt rex_formatter::date)
     *
     * @param array<int, string> $args [0] = date format (default: 'd.m.Y')
     */
    private static function date(string $content, array $args): string
    {
        $format = $args[0] ?? 'd.m.Y';

        $timestamp = strtotime($content);
        if (false === $timestamp) {
            return $content;
        }

        return \rex_formatter::date($timestamp, $format);
    }

    /**
     * Internationales Datum formatieren (nutzt rex_formatter::intlDate)
     *
     * @param array<int, string> $args [0] = format constant (FULL, LONG, MEDIUM, SHORT)
     */
    private static function intlDate(string $content, array $args): string
    {
        $timestamp = strtotime($content);
        if (false === $timestamp) {
            return $content;
        }

        $formatMap = [
            'FULL' => \IntlDateFormatter::FULL,
            'LONG' => \IntlDateFormatter::LONG,
            'MEDIUM' => \IntlDateFormatter::MEDIUM,
            'SHORT' => \IntlDateFormatter::SHORT,
        ];

        $formatName = strtoupper($args[0] ?? 'MEDIUM');
        $format = $formatMap[$formatName] ?? \IntlDateFormatter::MEDIUM;

        return \rex_formatter::intlDate($timestamp, $format);
    }

    /**
     * Zahl formatieren (nutzt rex_formatter::number)
     *
     * @param array<int, string> $args [0] = decimals, [1] = dec_point, [2] = thousands_sep
     */
    private static function number(string $content, array $args): string
    {
        $format = [];

        if (isset($args[0])) {
            $format['precision'] = (int) $args[0];
        }
        if (isset($args[1])) {
            $format['dec_point'] = $args[1];
        }
        if (isset($args[2])) {
            $format['thousands_sep'] = $args[2];
        }

        return \rex_formatter::number((float) $content, $format);
    }

    /**
     * Bytes formatieren (nutzt rex_formatter::bytes)
     *
     * @param array<int, string> $args [0] = precision
     */
    private static function bytes(string $content, array $args): string
    {
        $format = [];

        if (isset($args[0])) {
            $format['precision'] = (int) $args[0];
        }

        return \rex_formatter::bytes((int) $content, $format);
    }

    /**
     * URL-freundlichen Slug erstellen (nutzt rex_string::normalize)
     */
    private static function slug(string $content): string
    {
        return \rex_string::normalize($content);
    }

    /**
     * Als klickbaren Link ausgeben (nutzt rex_formatter::url)
     *
     * @param array<int, string> $args [0] = attr, [1] = params
     */
    private static function url(string $content, array $args): string
    {
        $format = [];

        if (isset($args[0])) {
            $format['attr'] = $args[0];
        }
        if (isset($args[1])) {
            $format['params'] = $args[1];
        }

        return \rex_formatter::url($content, $format);
    }

    /**
     * Als klickbare E-Mail ausgeben (nutzt rex_formatter::email)
     *
     * @param array<int, string> $args [0] = attr, [1] = params
     */
    private static function email(string $content, array $args): string
    {
        $format = [];

        if (isset($args[0])) {
            $format['attr'] = $args[0];
        }
        if (isset($args[1])) {
            $format['params'] = $args[1];
        }

        return \rex_formatter::email($content, $format);
    }

    /**
     * Verhindert einzelne Wörter am Zeilenende (nutzt rex_formatter::widont)
     */
    private static function widont(string $content): string
    {
        return \rex_formatter::widont($content);
    }

    /**
     * Syntax-Highlighting (nutzt rex_string::highlight)
     */
    private static function highlight(string $content): string
    {
        return \rex_string::highlight($content);
    }

    /**
     * Als JSON ausgeben
     */
    private static function json(string $content): string
    {
        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Base64-Kodierung
     */
    private static function base64(string $content): string
    {
        return base64_encode($content);
    }

    /**
     * Text umkehren
     */
    private static function reverse(string $content): string
    {
        $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);

        if (!$chars) {
            return $content;
        }

        return implode('', array_reverse($chars));
    }

    /**
     * Zeilenumbruch einfügen
     *
     * @param array<int, string> $args [0] = width (default: 75), [1] = break (default: "\n")
     */
    private static function wordwrap(string $content, array $args): string
    {
        $width = (int) ($args[0] ?? 75);
        $break = $args[1] ?? "\n";

        return wordwrap($content, $width, $break, true);
    }
}
