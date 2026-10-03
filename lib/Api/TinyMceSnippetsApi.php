<?php

namespace FriendsOfREDAXO\Snippets\Api;

use FriendsOfREDAXO\Snippets\Service\EditorItemsService;
use rex_api_function;
use rex_response;

/**
 * API-Endpoint für TinyMCE Snippets-Plugin.
 *
 * @package redaxo\snippets
 */
class TinyMceSnippetsApi extends rex_api_function
{
    protected $published = true;

    public function execute()
    {
        // Nur für angemeldete Backend-User mit Snippets-Rechten (auch bei Aufruf über das Frontend)
        $user = \rex::getUser() ?? (new \rex_backend_login())->getUser();
        if (null === $user) {
            rex_response::cleanOutputBuffers();
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'Access denied']);
            exit;
        }
        if (null === \rex::getUser()) {
            \rex::setProperty('user', $user);
        }
        if (!EditorItemsService::canUse()) {
            rex_response::cleanOutputBuffers();
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $categories = rex_request('categories', 'string', '');
        $categoriesArr = array_values(array_filter(array_map('trim', explode(',', $categories))));

        // Kategorie steht als category_id in rex_snippets_string, der Name in rex_snippets_category
        $sql = \rex_sql::factory();
        $query = 'SELECT s.key_name, c.name AS category FROM ' . \rex::getTable('snippets_string') . ' s'
            . ' LEFT JOIN ' . \rex::getTable('snippets_category') . ' c ON c.id = s.category_id'
            . ' WHERE s.status = 1';

        if ([] !== $categoriesArr) {
            $query .= ' AND c.name IN (' . $sql->in($categoriesArr) . ')';
        }

        $sql->setQuery($query);
        $rows = $sql->getArray();

        $data = [];
        foreach ($rows as $row) {
            $key = (string) $row['key_name'];
            $category = (string) ($row['category'] ?? '');
            $data[] = [
                'title' => '' !== $category ? $key . ' (' . $category . ')' : $key,
                'content' => '[[' . $key . ']]',
            ];
        }

        // Sortieren
        usort($data, static function (array $a, array $b): int {
            return strcasecmp($a['title'], $b['title']);
        });

        rex_response::cleanOutputBuffers();
        rex_response::sendJson($data);
        exit;
    }
}
