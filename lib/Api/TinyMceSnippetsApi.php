<?php

namespace FriendsOfREDAXO\Snippets\Api;

use rex_api_function;
use rex_response;
use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;
use FriendsOfREDAXO\Snippets\Service\SnippetsTranslate;

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
        $categories = rex_request('categories', 'string', '');
        $categoriesArr = array_filter(array_map('trim', explode(',', $categories)));

        // Alle verfügbaren Translation-Keys holen
        // Wir nutzen den Repository-Ansatz, um alle Keys zu bekommen
        $sql = \rex_sql::factory();
        $query = 'SELECT key_name, category FROM ' . \rex::getTable('snippets_string') . ' WHERE status = 1';
        
        if (!empty($categoriesArr)) {
            $query .= ' AND category IN (' . $sql->in($categoriesArr) . ')';
        }
        
        $sql->setQuery($query);
        $rows = $sql->getArray();
        
        $data = [];
        foreach ($rows as $row) {
            $key = (string) $row['key_name'];
            $data[] = [
                'title' => $key . ' (' . (string) $row['category'] . ')',
                'content' => '[[' . $key . ']]',
            ];
        }

        // Sortieren
        usort($data, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        rex_response::cleanOutputBuffers();
        rex_response::sendJson($data);
        exit;
    }
}
