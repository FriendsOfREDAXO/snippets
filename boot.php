<?php

/**
 * Snippets AddOn - Boot
 *
 * @package redaxo\snippets
 */

use FriendsOfREDAXO\Snippets\Service\ReplacementService;
use FriendsOfREDAXO\Snippets\Service\HtmlReplacementService;
use FriendsOfREDAXO\Snippets\Service\SnippetsTranslate;
use FriendsOfREDAXO\Snippets\Util\ContextDetector;

$addon = rex_addon::get('snippets');

// API-Funktion registrieren (Namespace-Registrierung, ab REDAXO 5.17)
rex_api_function::register('snippets_translations', FriendsOfREDAXO\Snippets\Api\TranslationsApi::class);
rex_api_function::register('snippets_tinymce_get', FriendsOfREDAXO\Snippets\Api\TinyMceSnippetsApi::class);

// Berechtigungen registrieren
if (rex::isBackend() && null !== rex::getUser()) {
    rex_perm::register('snippets[admin]', rex_i18n::msg('perm_general_snippets[admin]'));
    rex_perm::register('snippets[editor]', rex_i18n::msg('perm_general_snippets[editor]'));
    rex_perm::register('snippets[translate]', rex_i18n::msg('perm_general_snippets[translate]'));
    rex_perm::register('snippets[viewer]', rex_i18n::msg('perm_general_snippets[viewer]'));
}

// Assets im Backend laden
if (rex::isBackend() && null !== rex::getUser()) {
    rex_view::addJsFile(rex_url::addonAssets('snippets', 'snippets.js'));
    rex_view::addJsFile(rex_url::addonAssets('snippets', 'snippets-translations.js'));
    rex_view::addCssFile(rex_url::addonAssets('snippets', 'snippets.css'));
}

// Frontend: Snippet-Replacement
if (!rex::isBackend()) {
    $epFrontend = (string) $addon->getConfig('tstr_ep_frontend', 'OUTPUT_FILTER');
    
    // String-Übersetzungen ([[ key ]], optional {{ key }}) – läuft FRÜH, da andere Snippets darauf aufbauen können
    rex_extension::register($epFrontend, static function (rex_extension_point $ep) {
        return SnippetsTranslate::replace($ep->getSubject(), rex_clang::getCurrentId());
    }, rex_extension::EARLY);

    rex_extension::register($epFrontend, static function (rex_extension_point $ep) {
        return ReplacementService::replace($ep->getSubject(), [
            'clang_id' => rex_clang::getCurrentId(),
        ]);
    }, rex_extension::NORMAL);

    // HTML-Ersetzungen immer als letzter Filter ausführen
    rex_extension::register($epFrontend, static function (rex_extension_point $ep) {
        return HtmlReplacementService::process($ep->getSubject(), 'frontend');
    }, rex_extension::LATE);
}

// Backend: String-Übersetzungen in Slice-Vorschau (SLICE_BE_PREVIEW)
// Ersetzt [[ key ]]-Platzhalter nur im gerenderten Slice-Output (Vorschau-Modus),
// nicht in Formularen, Textareas oder während des Editiervorgangs.
if (rex::isBackend()) {
    $epBackend = (string) $addon->getConfig('tstr_ep_backend', 'SLICE_BE_PREVIEW');
    
    rex_extension::register($epBackend, static function (rex_extension_point $ep) {
        $clangId = (int) $ep->getParam('clang', rex_clang::getCurrentId());
        return SnippetsTranslate::replace($ep->getSubject(), $clangId);
    }, rex_extension::EARLY);
}

// Backend: Snippet-Replacement (nur in sicheren Kontexten)
if (rex::isBackend()) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        if (ContextDetector::isEditContext()) {
            return $ep->getSubject();
        }

        return ReplacementService::replace($ep->getSubject(), [
            'context' => 'backend',
            'clang_id' => rex_clang::getCurrentId(),
        ]);
    }, rex_extension::NORMAL);

    // HTML-Ersetzungen immer als letzter Filter ausführen
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        $currentPage = (string) rex_be_controller::getCurrentPage();
        // Keine HTML-Ersetzungen auf den Snippets-Seiten selbst, um sich nicht auszusperren
        if ('' !== $currentPage && str_starts_with($currentPage, 'snippets/')) {
            return $ep->getSubject();
        }

        return HtmlReplacementService::process(
            $ep->getSubject(),
            'backend',
            false // HTML-Ersetzungen immer zulassen, auch im Edit-Kontext (im Gegensatz zu normalen Snippets)
        );
    }, rex_extension::LATE);
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

// TinyMCE Plugin Integration
if (rex::isBackend() && rex::getUser() && rex_addon::get('tinymce')->isAvailable()) {
    if (class_exists(\FriendsOfRedaxo\TinyMce\PluginRegistry::class)) {
        \FriendsOfRedaxo\TinyMce\PluginRegistry::addPlugin(
            'redaxo_snippets',
            rex_url::addonAssets('snippets', 'js/tinymce-snippets.js'),
            'redaxo_snippets'
        );
    }
}
