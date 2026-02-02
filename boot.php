<?php

/**
 * Snippets AddOn - Boot
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\ReplacementService;
use FriendsOfREDAXO\Snippets\Service\HtmlReplacementService;
use FriendsOfREDAXO\Snippets\Service\AbbreviationService;
use FriendsOfREDAXO\Snippets\Util\ContextDetector;

// Helper-Funktionen laden
require_once __DIR__ . '/functions/snippets.php';

// Berechtigungen registrieren
if (rex::isBackend() && rex::getUser()) {
    rex_perm::register('snippets[admin]', rex_i18n::msg('perm_general_snippets[admin]'));
    rex_perm::register('snippets[editor]', rex_i18n::msg('perm_general_snippets[editor]'));
    rex_perm::register('snippets[viewer]', rex_i18n::msg('perm_general_snippets[viewer]'));
}

// Assets im Backend laden
if (rex::isBackend() && rex::getUser()) {
    rex_view::addJsFile(rex_url::addonAssets('snippets', 'snippets.js'));
}

// Frontend: Snippet-Replacement
if (!rex::isBackend()) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        $content = $ep->getSubject();

        // 1. Snippet-Keys ersetzen
        $service = new ReplacementService();
        $content = $service->replace($content, [
            'clang_id' => rex_clang::getCurrentId(),
        ]);

        // 2. HTML-Ersetzungen anwenden
        $content = HtmlReplacementService::process($content, 'frontend');

        // 3. Abkürzungen kennzeichnen
        $content = AbbreviationService::process($content, 'frontend', rex_clang::getCurrentId());

        return $content;
    }, rex_extension::NORMAL);
}

// Backend: Snippet-Replacement (nur in sicheren Kontexten)
if (rex::isBackend()) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        // Prüfen, ob wir uns in einem Edit-Kontext befinden
        if (ContextDetector::isEditContext()) {
            return $ep->getSubject();
        }

        $content = $ep->getSubject();

        // 1. Snippet-Keys ersetzen
        $service = new ReplacementService();
        $content = $service->replace($content, [
            'context' => 'backend',
            'clang_id' => rex_clang::getCurrentId(),
        ]);

        // 2. HTML-Ersetzungen anwenden
        $content = HtmlReplacementService::process($content, 'backend');

        // 3. Abkürzungen kennzeichnen
        $content = AbbreviationService::process($content, 'backend', rex_clang::getCurrentId());

        return $content;
    }, rex_extension::NORMAL);
}

// Sprog-Integration (falls Sprog aktiv ist)
if (rex_addon::get('sprog')->isAvailable()) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        if (ContextDetector::isEditContext()) {
            return $ep->getSubject();
        }

        $content = $ep->getSubject();

        // [[sprog:key]] über Sprog-Wildcards
        $pattern = '/\[\[sprog:([\w\-_]+)\]\]/';
        return preg_replace_callback($pattern, static function ($matches) {
            $key = $matches[1];

            // Sprog-Funktion nutzen, wenn verfügbar
            if (function_exists('sprogdown')) {
                return sprogdown($key);
            }

            // Fallback: Sprog-Wildcard API
            return \Wildcard::get($key);
        }, $content);
    }, rex_extension::LATE);
}
