<?php

namespace FriendsOfREDAXO\Snippets\Api;

use FriendsOfREDAXO\Snippets\Service\EditorItemsService;

/**
 * API-Endpunkt für Editor-Integrationen (CKEditor 5).
 *
 * Liefert Snippets und String-Übersetzungen als JSON:
 *   index.php?rex-api-call=snippets_editor_items[&clang=1][&types=snippets,translations][&categories=Website,3]
 *
 * Nur im Backend und nur für User mit Snippets-Rechten (admin, editor, viewer, translate).
 * Es werden ausschließlich Daten gelesen.
 *
 * @package redaxo\snippets
 */
class EditorItemsApi extends \rex_api_function
{
    /** Nur im Backend-Kontext aufrufbar */
    protected $published = false;

    public function execute(): never
    {
        \rex_response::cleanOutputBuffers();
        \rex_response::setHeader('Cache-Control', 'no-store, private');

        if (null === \rex::getUser() || !EditorItemsService::canUse()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => \rex_i18n::msg('snippets_cke5_error_permission')]);
            exit;
        }

        $clangId = \rex_request::get('clang', 'int', 0);
        if (null === \rex_clang::get($clangId)) {
            $clangId = \rex_clang::getCurrentId();
        }

        $data = EditorItemsService::build(
            $clangId,
            self::splitList(\rex_request::get('types', 'string', '')),
            self::splitList(\rex_request::get('categories', 'string', '')),
        );

        \rex_response::sendJson($data);
        exit;
    }

    /**
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => '' !== $item));
    }
}
