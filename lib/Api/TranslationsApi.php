<?php

namespace FriendsOfREDAXO\Snippets\Api;

use FriendsOfREDAXO\Snippets\Repository\TranslationStringRepository;
use FriendsOfREDAXO\Snippets\Service\SnippetsTranslate;
use FriendsOfREDAXO\Snippets\Service\PermissionService;

/**
 * API-Endpunkt für String-Übersetzungen
 *
 * Registrierung via rex_api_function::register() in boot.php
 *
 * Handles:
 * - save: Einzelnen Wert speichern (AJAX/PJAX inline)
 * - save_all: Alle Werte eines Strings speichern
 * - add: Neuen String anlegen
 * - delete: String löschen
 * - toggle_status: Status toggeln
 * - translate: DeepL-Übersetzung abrufen
 *
 * @package redaxo\snippets
 */
class TranslationsApi extends \rex_api_function
{
    protected $published = false;

    public function execute(): \rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!PermissionService::canEdit()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Keine Berechtigung']);
            exit;
        }

        $action = \rex_request::request('action', 'string', '');
        $csrfToken = \rex_csrf_token::factory('snippets_translations');

        // CSRF-Check für nicht-GET Requests (translate-Aktionen sind AJAX-only)
        if (!in_array($action, ['translate', 'batch_translate'], true) && !\rex_request::isXmlHttpRequest()) {
            if (!$csrfToken->isValid()) {
                \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
                \rex_response::sendJson(['error' => 'Ungültiges CSRF-Token']);
                exit;
            }
        }

        return match ($action) {
            'save' => $this->handleSave(),
            'save_all' => $this->handleSaveAll(),
            'add' => $this->handleAdd(),
            'delete' => $this->handleDelete(),
            'toggle_status' => $this->handleToggleStatus(),
            'translate' => $this->handleTranslate(),
            'batch_translate' => $this->handleBatchTranslate(),
            'update_category' => $this->handleUpdateCategory(),
            'update_key' => $this->handleUpdateKey(),
            default => $this->handleError('Unbekannte Aktion: ' . $action),
        };
    }

    private function handleSave(): \rex_api_result
    {
        $stringId = \rex_request::request('string_id', 'int', 0);
        $clangId = \rex_request::request('clang_id', 'int', 0);
        $value = \rex_request::request('value', 'string', '');

        if ($stringId <= 0 || $clangId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige Parameter']);
            exit;
        }

        TranslationStringRepository::saveValue($stringId, $clangId, $value);
        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'message' => \rex_i18n::msg('snippets_tstr_value_saved'),
        ]);
        exit;
    }

    private function handleSaveAll(): \rex_api_result
    {
        $stringId = \rex_request::request('string_id', 'int', 0);
        $values = \rex_request::request('values', 'array', []);

        if ($stringId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige String-ID']);
            exit;
        }

        $parsedValues = [];
        foreach ($values as $clangId => $value) {
            $parsedValues[(int) $clangId] = (string) $value;
        }

        TranslationStringRepository::saveValues($stringId, $parsedValues);
        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'message' => \rex_i18n::msg('snippets_tstr_saved'),
        ]);
        exit;
    }

    private function handleAdd(): \rex_api_result
    {
        $keyName = \rex_request::request('key_name', 'string', '');
        $categoryId = \rex_request::request('category_id', 'int', 0);

        // Validierung
        if ('' === $keyName) {
            \rex_response::sendJson(['success' => false, 'error' => \rex_i18n::msg('snippets_tstr_error_key_empty')]);
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $keyName)) {
            \rex_response::sendJson(['success' => false, 'error' => \rex_i18n::msg('snippets_error_invalid_key')]);
            exit;
        }

        if (TranslationStringRepository::keyExists($keyName)) {
            \rex_response::sendJson(['success' => false, 'error' => \rex_i18n::msg('snippets_tstr_error_key_exists')]);
            exit;
        }

        $data = [
            'key_name' => $keyName,
            'status' => 1,
        ];

        if ($categoryId > 0) {
            $data['category_id'] = $categoryId;
        }

        $id = TranslationStringRepository::save($data);

        // Werte speichern falls mitgesendet
        $values = \rex_request::request('values', 'array', []);
        if ([] !== $values) {
            $parsedValues = [];
            foreach ($values as $clangId => $value) {
                $parsedValues[(int) $clangId] = (string) $value;
            }
            TranslationStringRepository::saveValues($id, $parsedValues);
        }

        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'message' => \rex_i18n::msg('snippets_tstr_added'),
            'id' => $id,
        ]);
        exit;
    }

    private function handleDelete(): \rex_api_result
    {
        if (!PermissionService::isAdmin()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Keine Berechtigung']);
            exit;
        }

        $stringId = \rex_request::request('string_id', 'int', 0);

        if ($stringId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige String-ID']);
            exit;
        }

        TranslationStringRepository::delete($stringId);
        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'message' => \rex_i18n::msg('snippets_tstr_deleted'),
        ]);
        exit;
    }

    private function handleToggleStatus(): \rex_api_result
    {
        $stringId = \rex_request::request('string_id', 'int', 0);

        if ($stringId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige String-ID']);
            exit;
        }

        $entity = TranslationStringRepository::getById($stringId);
        if (null === $entity) {
            \rex_response::sendJson(['success' => false, 'error' => 'String nicht gefunden']);
            exit;
        }

        $newStatus = $entity->isActive() ? 0 : 1;
        TranslationStringRepository::save(['id' => $stringId, 'status' => $newStatus]);
        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'status' => $newStatus,
            'message' => \rex_i18n::msg('snippets_status_updated'),
        ]);
        exit;
    }

    private function handleUpdateCategory(): \rex_api_result
    {
        $stringId = \rex_request::request('string_id', 'int', 0);
        $categoryId = \rex_request::request('category_id', 'int', 0);

        if ($stringId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige String-ID']);
            exit;
        }

        TranslationStringRepository::save([
            'id' => $stringId,
            'category_id' => $categoryId > 0 ? $categoryId : null,
        ]);

        \rex_response::sendJson([
            'success' => true,
            'message' => \rex_i18n::msg('snippets_tstr_category_updated'),
        ]);
        exit;
    }

    /**
     * Key eines Strings umbenennen.
     */
    private function handleUpdateKey(): \rex_api_result
    {
        $stringId = \rex_request::request('string_id', 'int', 0);
        $newKey = trim(\rex_request::request('new_key', 'string', ''));

        if ($stringId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige String-ID']);
            exit;
        }

        if ('' === $newKey || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $newKey)) {
            \rex_response::sendJson(['success' => false, 'error' => \rex_i18n::msg('snippets_error_invalid_key')]);
            exit;
        }

        if (TranslationStringRepository::keyExists($newKey, $stringId)) {
            \rex_response::sendJson(['success' => false, 'error' => \rex_i18n::msg('snippets_tstr_error_key_exists')]);
            exit;
        }

        TranslationStringRepository::save([
            'id' => $stringId,
            'key_name' => $newKey,
        ]);
        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'key' => $newKey,
            'message' => \rex_i18n::msg('snippets_tstr_key_updated'),
        ]);
        exit;
    }

    private function handleTranslate(): \rex_api_result
    {
        $text = \rex_request::request('text', 'string', '');
        $targetClangCode = \rex_request::request('target_lang', 'string', '');
        $sourceClangCode = \rex_request::request('source_lang', 'string', '');
        $stringId = \rex_request::request('string_id', 'int', 0);
        $clangId = \rex_request::request('clang_id', 'int', 0);

        if ('' === $text || '' === $targetClangCode) {
            \rex_response::sendJson(['success' => false, 'error' => 'Text und Zielsprache erforderlich']);
            exit;
        }

        try {
            $translated = SnippetsTranslate::translateWithDeepL(
                $text,
                $targetClangCode,
                '' !== $sourceClangCode ? $sourceClangCode : null
            );

            // Optional: direkt in DB speichern wenn string_id und clang_id mitgesendet
            if ($stringId > 0 && $clangId > 0) {
                TranslationStringRepository::saveValue($stringId, $clangId, $translated);
                SnippetsTranslate::clearCache();
            }

            \rex_response::sendJson([
                'success' => true,
                'translated' => $translated,
            ]);
        } catch (\Exception $e) {
            \rex_response::sendJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }

        exit;
    }

    /**
     * Batch-Übersetzung: Alle Strings einer Zielsprache serverseitig übersetzen.
     */
    private function handleBatchTranslate(): \rex_api_result
    {
        $targetClangId = \rex_request::request('target_clang_id', 'int', 0);
        $emptyOnly = (bool) \rex_request::request('empty_only', 'int', 1);

        if ($targetClangId <= 0) {
            \rex_response::sendJson(['success' => false, 'error' => 'Zielsprache erforderlich']);
            exit;
        }

        $targetClang = \rex_clang::get($targetClangId);
        if (null === $targetClang) {
            \rex_response::sendJson(['success' => false, 'error' => 'Ungültige Zielsprache']);
            exit;
        }

        $targetClangCode = $targetClang->getCode();

        // Alle aktiven Strings laden
        $strings = TranslationStringRepository::findAll(['status' => 1]);

        // Konfigurierte Quellsprache verwenden (Default: erste Sprache)
        $sourceClangId = (int) \rex_addon::get('snippets')->getConfig('tstr_source_clang_id', \rex_clang::getStartId());
        $sourceClang = \rex_clang::get($sourceClangId);
        if (null === $sourceClang) {
            $sourceClang = \rex_clang::get(\rex_clang::getStartId());
        }

        if ($sourceClangId === $targetClangId) {
            \rex_response::sendJson(['success' => false, 'error' => \rex_i18n::msg('snippets_tstr_batch_source_equals_target')]);
            exit;
        }

        $translated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($strings as $string) {
            $currentValue = $string->getValue($targetClangId);

            // Wenn nur leere: überspringen wenn bereits gefüllt
            if ($emptyOnly && '' !== $currentValue) {
                $skipped++;
                continue;
            }

            // Quelltext aus konfigurierter Quellsprache lesen
            $sourceText = $string->getValue($sourceClang->getId());
            $sourceClangCode = $sourceClang->getCode();

            if ('' === $sourceText) {
                $skipped++;
                continue;
            }

            try {
                $result = SnippetsTranslate::translateWithDeepL(
                    $sourceText,
                    $targetClangCode,
                    $sourceClang->getCode()
                );
                TranslationStringRepository::saveValue($string->getId(), $targetClangId, $result);
                $translated++;
            } catch (\Exception $e) {
                $errors++;
            }

            // Kurze Pause für DeepL Rate Limits (50ms)
            usleep(50000);
        }

        SnippetsTranslate::clearCache();

        \rex_response::sendJson([
            'success' => true,
            'translated' => $translated,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => \rex_i18n::msg('snippets_tstr_batch_done', (string) $translated),
        ]);
        exit;
    }

    private function handleError(string $message): \rex_api_result
    {
        \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
        \rex_response::sendJson(['error' => $message]);
        exit;
    }
}
