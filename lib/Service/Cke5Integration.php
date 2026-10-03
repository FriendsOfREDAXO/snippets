<?php

namespace FriendsOfREDAXO\Snippets\Service;

/**
 * Registriert das CKEditor-5-Plugin "snippetsAddon" bei der PluginRegistry des cke5-AddOns.
 *
 * Aktiv wird das Plugin erst, wenn ein CKE5-Profil es über
 * {"externalPlugins": ["snippetsAddon"]} (Feld "Extra-Definition") einschaltet.
 *
 * @package redaxo\snippets
 */
class Cke5Integration
{
    public const PLUGIN_NAME = 'snippetsAddon';

    public static function register(): void
    {
        if (!class_exists(\Cke5\PluginRegistry::class) || !EditorItemsService::canUse()) {
            return;
        }

        $addon = \rex_addon::get('snippets');
        $jsUrl = $addon->getAssetsUrl('js/cke5-snippets.js');

        \Cke5\PluginRegistry::addPlugin(self::PLUGIN_NAME, $jsUrl, self::getClientConfig());

        // Das cke5-AddOn wird je nach Paket-Reihenfolge vor diesem AddOn gebootet und hat seine
        // Assets und die Registry dann bereits ausgegeben. In diesem Fall Skript und Konfiguration
        // selbst nachreichen (das Skript registriert sich vor dem ersten rex:ready).
        $jsFiles = \rex_view::getJsFiles();
        $cke5AlreadyLoaded = [] !== array_filter($jsFiles, static fn (string $file): bool => str_contains($file, 'addons/cke5/cke5.js'));
        if ($cke5AlreadyLoaded) {
            if (!in_array($jsUrl, $jsFiles, true)) {
                \rex_view::addJsFile($jsUrl);
            }
            \rex_view::setJsProperty('cke5ExternalPlugins', \Cke5\PluginRegistry::clientConfig());
        }

        \rex_view::addCssFile($addon->getAssetsUrl('css/cke5-snippets.css'));
    }

    /**
     * Konfiguration, die als rex.cke5ExternalPlugins.snippetsAddon.config im Browser landet.
     *
     * Achtung: Diese Daten werden im Backend-HTML ausgegeben und laufen durch den OUTPUT_FILTER.
     * Daher keine Platzhalter-Syntax in Texten verwenden – die baut das Skript selbst zusammen.
     *
     * @return array<string, mixed>
     */
    public static function getClientConfig(): array
    {
        $addon = \rex_addon::get('snippets');

        return [
            'apiUrl' => \rex_url::backendController(['rex-api-call' => 'snippets_editor_items'], false),
            'defaults' => [
                'types' => [EditorItemsService::TYPE_SNIPPETS, EditorItemsService::TYPE_TRANSLATIONS],
                'categories' => [],
                'toolbar' => true,
                'autocomplete' => true,
                'highlight' => true,
                'sprogSyntax' => (bool) $addon->getConfig('tstr_sprog_syntax', false),
            ],
            'i18n' => self::getTranslations(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function getTranslations(): array
    {
        $keys = [
            'button', 'search', 'search_label', 'tab_all', 'tab_snippets', 'tab_translations',
            'group_snippets', 'group_translations', 'no_category', 'loading', 'empty', 'no_results',
            'error', 'retry', 'more_results', 'results', 'type_html', 'type_text', 'type_php', 'type_translation',
            'missing_value', 'params_title', 'params_help', 'params_insert', 'params_back',
            'manage', 'keyboard_hint', 'unknown_placeholder', 'inactive_placeholder', 'quickedit_label',
        ];

        $translations = [];
        foreach ($keys as $key) {
            $translations[$key] = \rex_i18n::rawMsg('snippets_cke5_' . $key);
        }

        return $translations;
    }
}
