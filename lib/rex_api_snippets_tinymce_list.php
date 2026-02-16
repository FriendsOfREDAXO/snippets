<?php

class rex_api_snippets_tinymce_list extends rex_api_function
{
    protected $published = true;  // Publicly accessible via API call

    public function execute()
    {
        // Try to get the backend user, even if we are in frontend context (API call)
        $user = rex::getUser();
        if (!$user && rex::isBackend()) {
            $user = rex::getUser();
        } elseif (!$user) {
             // If frontend request, try to get backend user from session
             $login = new rex_backend_login();
             $user = $login->getUser();
        }
        
        if (!$user) {
             // Debugging: Warn trace
             // rex_logger::logError(E_WARNING, 'Snippets API: Access Denied (No User)', __FILE__, __LINE__);
             
             rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
             rex_response::sendJson(['error' => 'Access denied (User not found)']);
             exit;
        }

        // $user = $user->getComplexPerm('snippets') ? $user : null; // Is this robust? No.
        
        // Check permissions safely
        if (!$user->isAdmin() && !$user->hasPerm('snippets[]') && !$user->hasPerm('snippets[admin]') && !$user->hasPerm('snippets[editor]')) {
             rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
             rex_response::sendJson(['error' => 'Permission denied']);
             exit;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('snippets_snippet'));
        $sql->setWhere(['status' => 1]);
        $sql->select('key_name, title, description, content');
        $snippets = $sql->getArray();

        // Sort by title
        usort($snippets, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        $data = [];
        foreach ($snippets as $snippet) {
            $label = $snippet['title'];
            if ($snippet['key_name']) {
                $label .= ' (' . $snippet['key_name'] . ')';
            }
            
            $data[] = [
                'value' => $snippet['key_name'],
                'text' => $label,
                'content' => $snippet['content'], // Optional: Can be used for preview
                'description' => $snippet['description']
            ];
        }

        rex_response::cleanOutputBuffers();
        rex_response::sendJson($data);
        exit;
    }
}
