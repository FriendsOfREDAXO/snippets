<?php

namespace FriendsOfREDAXO\Snippets\Util;

/**
 * Context-Detector für sicheres Snippet-Rendering
 *
 * Verhindert Ersetzung in Edit-Kontexten (Formulare, Module-Edit, YForm-Edit etc.)
 *
 * @package redaxo\snippets
 */
class ContextDetector
{
    /**
     * Prüft, ob wir uns in einem Edit-Kontext befinden (Backend-Formulare)
     */
    public static function isEditContext(): bool
    {
        if (!\rex::isBackend()) {
            return false;
        }

        $page = \rex_be_controller::getCurrentPage();
        $func = \rex_request::request('func', 'string', '');

        // Snippets-AddOn selbst (keine Ersetzung in eigener Verwaltung!)
        if (str_starts_with($page, 'snippets/')) {
            return true;
        }

        // Struktur-Edit-Modus
        if ('content/edit' === $page) {
            return true;
        }

        // YForm Table Manager Edit
        if (str_contains($page, 'yform/manager/data_edit')) {
            return true;
        }

        // Module-Edit-Seiten
        if (str_contains($page, 'content/modules')) {
            return true;
        }

        // Template-Edit-Seiten
        if (str_contains($page, 'content/templates') && 'edit' === $func) {
            return true;
        }

        // Ajax-Requests mit Edit-Funktionen
        if (\rex_request::isXmlHttpRequest()) {
            if (in_array($func, ['edit', 'add'], true)) {
                return true;
            }
        }

        // MBlock/MForm Edit-Modes (POST-Parameter)
        $mblockAction = \rex_request::post('mblock_action', 'string', '');
        $mformSend = \rex_request::post('mform_send', 'string', '');
        if ('' !== $mblockAction || '' !== $mformSend) {
            return true;
        }

        // REX_VALUE Edit-Context erkennen
        $apiCall = \rex_request::request('rex-api-call', 'string', '');
        if ('' !== $apiCall) {
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob wir uns im Frontend befinden
     */
    public static function isFrontend(): bool
    {
        return !\rex::isBackend();
    }

    /**
     * Prüft, ob wir uns im Backend befinden (aber nicht im Edit-Modus)
     */
    public static function isBackendSafe(): bool
    {
        return \rex::isBackend() && !self::isEditContext();
    }
}
