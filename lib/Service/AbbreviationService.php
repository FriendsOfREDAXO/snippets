<?php

/**
 * Snippets AddOn - Abbreviation Service
 * 
 * Nutzt PHP 8.4 DOM API für maximale Performance
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Domain\Abbreviation;
use FriendsOfREDAXO\Snippets\Repository\AbbreviationRepository;
use Dom\HTMLDocument;
use Dom\Element;
use Dom\Text;
use rex;
use rex_article;
use rex_clang;

/**
 * Service für Abkürzungs-Verarbeitung mit PHP 8.4 DOM
 */
class AbbreviationService
{
    /**
     * Verarbeitet HTML und ersetzt Abkürzungen
     * 
     * @param string $content HTML-Content
     * @param string $context 'frontend' oder 'backend'
     * @return string Verarbeiteter Content
     */
    public static function process(string $content, string $context = 'frontend'): string
    {
        // Früh-Return wenn Content leer oder zu klein
        if ('' === trim($content) || strlen($content) < 10) {
            return $content;
        }

        $clangId = rex_clang::getCurrentId();
        
        // Aktive Abkürzungen laden
        $abbreviations = AbbreviationRepository::findActiveForContext($context, $clangId);
        
        if ([] === $abbreviations) {
            return $content;
        }

        // Nach Scope filtern
        $abbreviations = self::filterByScope($abbreviations, $context);
        
        if ([] === $abbreviations) {
            return $content;
        }

        return self::applyAbbreviations($content, $abbreviations);
    }

    /**
     * Filtert Abkürzungen nach Scope
     *
     * @param Abbreviation[] $abbreviations
     * @return Abbreviation[]
     */
    private static function filterByScope(array $abbreviations, string $context): array
    {
        $filtered = [];

        foreach ($abbreviations as $abbr) {
            if (!$abbr->appliesToContext($context)) {
                continue;
            }

            // Frontend: Template/Kategorie/URL-Check
            if ($context === Abbreviation::CONTEXT_FRONTEND) {
                $article = rex_article::getCurrent();
                if ($article instanceof rex_article) {
                    $templateId = $article->getTemplateId();
                    if ($templateId > 0 && !$abbr->appliesToTemplate($templateId)) {
                        continue;
                    }

                    $categoryId = $article->getCategoryId();
                    if ($categoryId > 0 && !$abbr->appliesToCategory($categoryId)) {
                        continue;
                    }
                }

                $url = rex_server('REQUEST_URI', 'string', '');
                if (!$abbr->appliesToUrl($url)) {
                    continue;
                }
            }

            $filtered[] = $abbr;
        }

        return $filtered;
    }

    /**
     * Wendet Abkürzungen auf HTML an - PHP 8.4 DOM
     *
     * @param string $content
     * @param Abbreviation[] $abbreviations
     * @return string
     */
    private static function applyAbbreviations(string $content, array $abbreviations): string
    {
        try {
            // PHP 8.4 HTMLDocument - automatisch HTML5-konform
            $doc = HTMLDocument::createFromString($content, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            if (!$doc->body) {
                return $content;
            }

            // Build exclude list mit CSS-Selektoren aus Settings
            $excludeSelectors = self::getExcludeSelectorsFromSettings();
            
            // Build regex patterns für Performance
            $patterns = self::buildPatterns($abbreviations);
            
            // Rekursiv durch DOM-Tree
            self::processNode($doc->body, $patterns, $abbreviations, $excludeSelectors);
            
            // PHP 8.4: saveHTML() ohne Warnings
            return $doc->saveHTML();
            
        } catch (\Exception $e) {
            // Bei Fehlern Original zurückgeben
            return $content;
        }
    }

    /**
     * Lädt globale Exclude-Selektoren aus Addon-Settings
     *
     * @return array<string>
     */
    private static function getExcludeSelectorsFromSettings(): array
    {
        $addon = \rex_addon::get('snippets');
        $excludeSelectors = $addon->getConfig('abbreviation_exclude_selectors', '');
        
        if ('' === trim($excludeSelectors)) {
            // Defaults: Links und bestimmte Tags
            return ['a', 'nav', 'code', 'pre'];
        }
        
        // Ein Selektor pro Zeile
        return array_filter(
            array_map('trim', explode("\n", $excludeSelectors)),
            fn($s) => '' !== $s
        );
    }

    /**
     * Baut Regex-Patterns für alle Abkürzungen
     * 
     * @param Abbreviation[] $abbreviations
     * @return array<int, string>
     */
    private static function buildPatterns(array $abbreviations): array
    {
        $patterns = [];
        
        foreach ($abbreviations as $abbr) {
            $pattern = preg_quote($abbr->getAbbr(), '/');
            
            if ($abbr->isWholeWord()) {
                $pattern = '\b' . $pattern . '\b';
            }
            
            $flags = 'u'; // Unicode
            if (!$abbr->isCaseSensitive()) {
                $flags .= 'i';
            }
            
            $patterns[$abbr->getId()] = '/' . $pattern . '/' . $flags;
        }
        
        return $patterns;
    }

    /**
     * Verarbeitet einen DOM-Knoten rekursiv
     *
     * @param array<int, string> $patterns
     * @param Abbreviation[] $abbreviations
     * @param array<string> $excludeSelectors
     */
    private static function processNode(Element|Text $node, array $patterns, array $abbreviations, array $excludeSelectors): void
    {
        // Skip bestimmte Elements (pre, code, script, style, abbr, a)
        if ($node instanceof Element) {
            $tagName = strtolower($node->nodeName);
            
            // Immer skippen: pre, code, script, style, abbr, textarea, a (Links)
            if (in_array($tagName, ['pre', 'code', 'script', 'style', 'abbr', 'textarea', 'a'], true)) {
                return;
            }
            
            // Prüfe ob Element durch Exclude-Selektor ausgeschlossen ist
            if (self::matchesExcludeSelector($node, $excludeSelectors)) {
                return;
            }
            
            // Kinder verarbeiten
            foreach ($node->childNodes as $child) {
                if ($child instanceof Element || $child instanceof Text) {
                    self::processNode($child, $patterns, $abbreviations, $excludeSelectors);
                }
            }
            
            return;
        }

        // Text-Knoten verarbeiten
        if ($node instanceof Text) {
            $text = $node->textContent;
            
            if ('' === trim($text)) {
                return;
            }
            
            $modified = false;
            $matches = [];
            
            // Finde alle Matches in diesem Text-Knoten
            foreach ($patterns as $abbrId => $pattern) {
                if (preg_match_all($pattern, $text, $found, PREG_OFFSET_CAPTURE)) {
                    foreach ($found[0] as $match) {
                        $matches[] = [
                            'abbr_id' => $abbrId,
                            'text' => $match[0],
                            'offset' => $match[1],
                        ];
                    }
                }
            }
            
            if ([] === $matches) {
                return;
            }
            
            // Nach Offset sortieren (rückwärts, um Offsets nicht zu verschieben)
            usort($matches, fn($a, $b) => $b['offset'] <=> $a['offset']);
            
            // Abkürzungen ersetzen (von hinten nach vorne)
            foreach ($matches as $match) {
                $abbr = null;
                foreach ($abbreviations as $a) {
                    if ($a->getId() === $match['abbr_id']) {
                        $abbr = $a;
                        break;
                    }
                }
                
                if (!$abbr) {
                    continue;
                }
                
                // Text in 3 Teile splitten
                $before = substr($text, 0, $match['offset']);
                $matchText = $match['text'];
                $after = substr($text, $match['offset'] + strlen($matchText));
                
                // PHP 8.4 DOM: createElement mit Attributen
                $abbrElement = $node->ownerDocument->createElement('abbr');
                $abbrElement->setAttribute('title', $abbr->getTitle());
                if ($abbr->getLanguage()) {
                    $abbrElement->setAttribute('lang', $abbr->getLanguage());
                }
                $abbrElement->textContent = $matchText;
                
                // Neuen Container erstellen
                $parent = $node->parentNode;
                
                if ($before !== '') {
                    $parent->insertBefore($node->ownerDocument->createTextNode($before), $node);
                }
                
                $parent->insertBefore($abbrElement, $node);
                
                if ($after !== '') {
                    $text = $after; // Für nächste Iteration
                    $node->textContent = $after;
                } else {
                    $parent->removeChild($node);
                    break; // Knoten entfernt, fertig
                }
            }
        }
    }

    /**
     * Prüft ob ein Element durch einen Exclude-Selektor ausgeschlossen ist
     *
     * @param Element $element
     * @param array<string> $excludeSelectors
     * @return bool
     */
    private static function matchesExcludeSelector(Element $element, array $excludeSelectors): bool
    {
        if (empty($excludeSelectors)) {
            return false;
        }
        
        foreach ($excludeSelectors as $selector) {
            $selector = trim($selector);
            
            if ('' === $selector) {
                continue;
            }
            
            // ID-Selektor: #main
            if (str_starts_with($selector, '#')) {
                $id = substr($selector, 1);
                if ($element->getAttribute('id') === $id) {
                    return true;
                }
            }
            
            // Klassen-Selektor: .no-abbr
            elseif (str_starts_with($selector, '.')) {
                $class = substr($selector, 1);
                $classList = $element->getAttribute('class');
                if ($classList && str_contains($classList, $class)) {
                    return true;
                }
            }
            
            // Tag-Selektor: nav, header, footer
            else {
                if (strtolower($element->nodeName) === strtolower($selector)) {
                    return true;
                }
            }
        }
        
        // Rekursiv Parent-Elemente prüfen (Kind von excluded Element)
        $parent = $element->parentNode;
        if ($parent instanceof Element) {
            return self::matchesExcludeSelector($parent, $excludeSelectors);
        }
        
        return false;
    }
}
