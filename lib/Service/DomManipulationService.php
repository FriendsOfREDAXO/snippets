<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Domain\Snippet;

/**
 * Service für DOM-Manipulation
 *
 * @package redaxo\snippets
 */
class DomManipulationService
{
    /**
     * Manipuliert HTML-Content basierend auf Snippet-Konfiguration
     */
    public static function manipulate(string $content, Snippet $snippet, string $replacementContent): string
    {
        if (!$snippet->hasHtmlMode()) {
            return $content;
        }

        $selector = $snippet->getHtmlSelector();
        $mode = $snippet->getHtmlMode();
        $position = $snippet->getHtmlPosition() ?? 'replace';

        if (empty($selector)) {
            return $content;
        }

        // DOMDocument für HTML-Parsing
        $dom = new \DOMDocument('1.0', 'UTF-8');
        
        // Fehler unterdrücken für ungültiges HTML
        $previousErrorSetting = libxml_use_internal_errors(true);
        
        // UTF-8 Meta-Tag hinzufügen für korrekte Kodierung
        $htmlWithMeta = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' 
                       . $content 
                       . '</body></html>';
        
        $dom->loadHTML($htmlWithMeta, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        $xpath = new \DOMXPath($dom);
        
        // CSS-Selector in XPath umwandeln
        $xpathQuery = self::cssToXPath($selector);
        
        if (empty($xpathQuery)) {
            return $content;
        }

        $nodes = $xpath->query($xpathQuery);
        
        if (false === $nodes || 0 === $nodes->length) {
            return $content;
        }

        // Replacement-Content als DOM-Fragment vorbereiten
        $fragment = $dom->createDocumentFragment();
        $fragment->appendXML($replacementContent);

        foreach ($nodes as $node) {
            switch ($mode) {
                case 'selector':
                    switch ($position) {
                        case 'replace':
                            // Ersetzt das komplette Element
                            if ($node->parentNode) {
                                $node->parentNode->replaceChild($fragment->cloneNode(true), $node);
                            }
                            break;
                        
                        case 'before':
                            // Fügt VOR dem Element ein
                            if ($node->parentNode) {
                                $node->parentNode->insertBefore($fragment->cloneNode(true), $node);
                            }
                            break;
                        
                        case 'after':
                            // Fügt NACH dem Element ein
                            if ($node->parentNode) {
                                if ($node->nextSibling) {
                                    $node->parentNode->insertBefore($fragment->cloneNode(true), $node->nextSibling);
                                } else {
                                    $node->parentNode->appendChild($fragment->cloneNode(true));
                                }
                            }
                            break;
                        
                        case 'prepend':
                            // Fügt als erstes Kind ein
                            if ($node->firstChild) {
                                $node->insertBefore($fragment->cloneNode(true), $node->firstChild);
                            } else {
                                $node->appendChild($fragment->cloneNode(true));
                            }
                            break;
                        
                        case 'append':
                            // Fügt als letztes Kind ein
                            $node->appendChild($fragment->cloneNode(true));
                            break;
                    }
                    break;
            }
        }

        // HTML zurückgeben (ohne das hinzugefügte Wrapper-HTML)
        $bodyNode = $dom->getElementsByTagName('body')->item(0);
        
        if (!$bodyNode) {
            return $content;
        }

        $result = '';
        foreach ($bodyNode->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    /**
     * Konvertiert CSS-Selector zu XPath
     */
    private static function cssToXPath(string $selector): string
    {
        // Einfache CSS-Selektoren unterstützen
        $selector = trim($selector);
        
        // ID-Selector: #id
        if (str_starts_with($selector, '#')) {
            $id = substr($selector, 1);
            return "//*[@id='{$id}']";
        }
        
        // Class-Selector: .class
        if (str_starts_with($selector, '.')) {
            $class = substr($selector, 1);
            return "//*[contains(@class, '{$class}')]";
        }
        
        // Element-Selector: h1, div, etc.
        if (preg_match('/^[a-z][a-z0-9]*$/i', $selector)) {
            return "//{$selector}";
        }
        
        // Element mit Klasse: h1.heading
        if (preg_match('/^([a-z][a-z0-9]*)\.([a-z0-9\-_]+)$/i', $selector, $matches)) {
            return "//{$matches[1]}[contains(@class, '{$matches[2]}')]";
        }
        
        // Element mit ID: h1#heading
        if (preg_match('/^([a-z][a-z0-9]*)#([a-z0-9\-_]+)$/i', $selector, $matches)) {
            return "//{$matches[1]}[@id='{$matches[2]}']";
        }
        
        // Attribut-Selector: [data-id="test"]
        if (preg_match('/^\[([a-z\-]+)=["\']([^"\']+)["\']\]$/i', $selector, $matches)) {
            return "//*[@{$matches[1]}='{$matches[2]}']";
        }

        return '';
    }
}
