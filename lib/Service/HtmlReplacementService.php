<?php

/**
 * Snippets AddOn - HTML Replacement Service
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets\Service;

use Dom\HTMLDocument;
use Dom\Element;
use Dom\XPath;
use FriendsOfREDAXO\Snippets\Domain\HtmlReplacement;
use FriendsOfREDAXO\Snippets\Repository\HtmlReplacementRepository;
use rex;
use rex_addon;
use rex_article;
use rex_be_controller;
use rex_clang;
use rex_request;

/**
 * Service für HTML-Ersetzung
 */
class HtmlReplacementService
{
    private static ?bool $allowSnippetsInHtmlReplacements = null;

    /**
     * Führt alle Ersetzungen aus
     */
    public static function process(string $content, string $context): string
    {
        $replacements = HtmlReplacementRepository::findActiveForContext($context);
        
        if ([] === $replacements) {
            return $content;
        }

        // Scope-Filter anwenden
        $applicableReplacements = self::filterByScope($replacements, $context);

        if ([] === $applicableReplacements) {
            return $content;
        }

        // Nach Priorität sortiert ausführen
        foreach ($applicableReplacements as $replacement) {
            $content = self::applyReplacement($content, $replacement);
        }

        return $content;
    }

    /**
     * Filtert Regeln nach Scope
     *
     * @param HtmlReplacement[] $replacements
     * @return HtmlReplacement[]
     */
    private static function filterByScope(array $replacements, string $context): array
    {
        $filtered = [];

        foreach ($replacements as $replacement) {
            if (!$replacement->appliesToContext($context)) {
                continue;
            }

            // Frontend: Template-Check
            if ($context === HtmlReplacement::CONTEXT_FRONTEND) {
                $article = rex_article::getCurrent();
                if ($article instanceof rex_article) {
                    $templateId = $article->getTemplateId();
                    if ($templateId > 0 && !$replacement->appliesToTemplate($templateId)) {
                        continue;
                    }

                    $categoryId = $article->getCategoryId();
                    if ($categoryId > 0 && !$replacement->appliesToCategory($categoryId)) {
                        continue;
                    }
                }

                // URL-Pattern check
                $url = rex_server('REQUEST_URI', 'string', '');
                if (!$replacement->appliesToUrl($url)) {
                    continue;
                }
            }

            // Backend: Seiten-Check
            if ($context === HtmlReplacement::CONTEXT_BACKEND) {
                $page = (string) rex_be_controller::getCurrentPage();
                if ('' === $page) {
                    $page = rex_request::get('page', 'string', '');
                }

                if (!$replacement->appliesToBackendPage($page)) {
                    continue;
                }

                $requestUri = rex_server('REQUEST_URI', 'string', '');
                $queryParams = [];
                $queryString = parse_url($requestUri, PHP_URL_QUERY);
                if (is_string($queryString) && '' !== $queryString) {
                    parse_str($queryString, $queryParams);
                }
                if (!$replacement->appliesToBackendRequest($requestUri, $queryParams)) {
                    continue;
                }
            }

            $filtered[] = $replacement;
        }

        return $filtered;
    }

    /**
     * Führt eine einzelne Ersetzung aus
     */
    private static function applyReplacement(string $content, HtmlReplacement $replacement): string
    {
        switch ($replacement->getType()) {
            case HtmlReplacement::TYPE_CSS_SELECTOR:
                return self::applyCssSelector($content, $replacement);
            
            case HtmlReplacement::TYPE_HTML_MATCH:
                return self::applyHtmlMatch($content, $replacement);
            
            case HtmlReplacement::TYPE_REGEX:
                return self::applyRegex($content, $replacement);
            
            case HtmlReplacement::TYPE_PHP_CALLBACK:
                return self::applyPhpCallback($content, $replacement);
            
            default:
                return $content;
        }
    }

    /**
     * Ersetzung via CSS-Selektor
     */
    private static function applyCssSelector(string $content, HtmlReplacement $replacement): string
    {
        if ('' === trim($content)) {
            return $content;
        }

        try {
            // PHP 8.4 HTML5 Parser verwenden
            $dom = HTMLDocument::createFromString($content);
            
            $xpath = new XPath($dom);
            $xpathQuery = self::cssToXPath($replacement->getSearchValue());

            $nodes = $xpath->evaluate($xpathQuery);
            
            if (!$nodes instanceof \Dom\NodeList || 0 === $nodes->count()) {
                return $content;
            }

            $replacementContent = $replacement->getReplacement();
            $replacementContent = self::prepareReplacementContent($replacementContent);
            $position = $replacement->getPosition();

            // Knoten rückwärts verarbeiten um Indexprobleme zu vermeiden
            for ($i = $nodes->count() - 1; $i >= 0; --$i) {
                $node = $nodes->item($i);
                
                if (!$node instanceof Element) {
                    continue;
                }

                self::manipulateNode($node, $replacementContent, $position, $dom);
            }

            return $dom->saveHTML();
            
        } catch (\Exception $e) {
            // Bei Fehlern Original zurückgeben
            return $content;
        }
    }

    /**
     * Manipuliert einen DOM-Knoten
     */
    private static function manipulateNode(
        Element $node, 
        string $replacement, 
        string $position,
        HTMLDocument $dom
    ): void {
        // Fragment aus HTML-String erstellen
        $tempContainer = $dom->createElement('div');
        $tempContainer->innerHTML = $replacement;

        switch ($position) {
            case HtmlReplacement::POSITION_REPLACE:
                // Alle Kinder des temp Containers einfügen
                while ($tempContainer->firstChild) {
                    $node->parentNode?->insertBefore(
                        $tempContainer->firstChild,
                        $node
                    );
                }
                $node->parentNode?->removeChild($node);
                break;

            case HtmlReplacement::POSITION_BEFORE:
                while ($tempContainer->firstChild) {
                    $node->parentNode?->insertBefore(
                        $tempContainer->firstChild,
                        $node
                    );
                }
                break;

            case HtmlReplacement::POSITION_AFTER:
                $nextSibling = $node->nextSibling;
                while ($tempContainer->firstChild) {
                    if ($nextSibling) {
                        $node->parentNode?->insertBefore(
                            $tempContainer->firstChild,
                            $nextSibling
                        );
                    } else {
                        $node->parentNode?->appendChild($tempContainer->firstChild);
                    }
                }
                break;

            case HtmlReplacement::POSITION_PREPEND:
                while ($tempContainer->lastChild) {
                    if ($node->firstChild) {
                        $node->insertBefore(
                            $tempContainer->lastChild,
                            $node->firstChild
                        );
                    } else {
                        $node->appendChild($tempContainer->lastChild);
                    }
                }
                break;

            case HtmlReplacement::POSITION_APPEND:
                while ($tempContainer->firstChild) {
                    $node->appendChild($tempContainer->firstChild);
                }
                break;
        }
    }

    /**
     * Konvertiert CSS-Selektor zu XPath
     */
    private static function cssToXPath(string $selector): string
    {
        $selector = trim($selector);

        // Einfache Konvertierungen
        $patterns = [
            '/^#([\w-]+)$/' => "//*[@id='$1']",                    // #id
            '/^\.([\w-]+)$/' => "//*[contains(@class,'$1')]",      // .class
            '/^([\w-]+)$/' => "//$1",                               // tag
            '/^([\w-]+)\.([\w-]+)$/' => "//$1[contains(@class,'$2')]", // tag.class
            '/^([\w-]+)#([\w-]+)$/' => "//$1[@id='$2']",           // tag#id
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (1 === preg_match($pattern, $selector)) {
                return (string) preg_replace($pattern, $replacement, $selector);
            }
        }

        // Fallback für komplexere Selektoren
        return "//*[@class='$selector' or @id='$selector']";
    }

    /**
     * Ersetzung via HTML-Code-Match
     */
    private static function applyHtmlMatch(string $content, HtmlReplacement $replacement): string
    {
        $search = $replacement->getSearchValue();
        $replace = self::prepareReplacementContent($replacement->getReplacement());

        return str_replace($search, $replace, $content);
    }

    /**
     * Ersetzung via Regex
     */
    private static function applyRegex(string $content, HtmlReplacement $replacement): string
    {
        $pattern = $replacement->getSearchValue();
        $replace = self::prepareReplacementContent($replacement->getReplacement());

        try {
            $result = preg_replace($pattern, $replace, $content);
            return $result ?? $content;
        } catch (\Exception $e) {
            // Bei Regex-Fehlern Original zurückgeben
            return $content;
        }
    }

    /**
     * Ersetzung via PHP Callback
     * Format: ClassName::methodName im replacement
     * Die Methode bekommt den gefundenen Match und gibt den Ersatz zurück
     */
    private static function applyPhpCallback(string $content, HtmlReplacement $replacement): string
    {
        $searchValue = $replacement->getSearchValue();
        $callback = $replacement->getReplacement();
        
        // Format: ClassName::methodName
        if (!str_contains($callback, '::')) {
            return $content;
        }
        
        [$className, $methodName] = explode('::', $callback, 2);
        
        // Sicherheit: Nur Klassen mit bestimmten Namespaces erlauben
        $allowedPrefixes = [
            'FriendsOfREDAXO\\',
            'rex_',
        ];
        
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($className, $prefix)) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            return $content;
        }
        
        // Prüfen ob Klasse und Methode existieren
        if (!class_exists($className)) {
            return $content;
        }
        
        if (!method_exists($className, $methodName)) {
            return $content;
        }
        
        try {
            // Callback aufrufen - bekommt search value und gibt Ersatz zurück
            // Callback kann auch preg_replace_callback nutzen
            $result = $className::$methodName($searchValue, $content, $replacement);
            return is_string($result) ? $result : $content;
        } catch (\Exception $e) {
            // Bei Fehlern Original zurückgeben
            return $content;
        }
    }

    private static function prepareReplacementContent(string $replacementContent): string
    {
        if (!self::isSnippetReplacementEnabled()) {
            return $replacementContent;
        }

        return ReplacementService::replace($replacementContent, [
            'context' => rex::isBackend() ? 'backend' : 'frontend',
            'clang_id' => rex_clang::getCurrentId(),
        ]);
    }

    private static function isSnippetReplacementEnabled(): bool
    {
        if (null !== self::$allowSnippetsInHtmlReplacements) {
            return self::$allowSnippetsInHtmlReplacements;
        }

        self::$allowSnippetsInHtmlReplacements = (bool) rex_addon::get('snippets')->getConfig('html_replacement_allow_snippets', false);

        return self::$allowSnippetsInHtmlReplacements;
    }
}
