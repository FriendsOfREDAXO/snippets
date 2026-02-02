<?php

namespace FriendsOfREDAXO\Snippets\Service;

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use FriendsOfREDAXO\Snippets\Domain\Snippet;

/**
 * Service für Snippet-Rendering
 *
 * @package redaxo\snippets
 */
class SnippetService
{
    /**
     * Rendert ein Snippet
     *
     * @param array<string, mixed> $params
     * @throws \rex_exception
     */
    public static function render(
        string $key,
        array $params = [],
        int $clangId = 1,
        string $context = 'frontend'
    ): string {
        $snippet = SnippetRepository::getByKey($key);

        if (null === $snippet) {
            throw new \rex_exception('Snippet "' . $key . '" not found');
        }

        if (!$snippet->isActive()) {
            return '';
        }

        // Context-Check
        $snippetContext = $snippet->getContext();
        if ('both' !== $snippetContext && $snippetContext !== $context) {
            return '';
        }

        // Content holen (mehrsprachig falls vorhanden)
        $content = self::getContent($snippet, $clangId);

        if ('' === $content) {
            return '';
        }

        // Je nach Content-Type rendern
        return match ($snippet->getContentType()) {
            'php' => self::renderPhp($snippet, $content, $params),
            'html', 'text' => self::renderTemplate($content, $params),
            default => $content,
        };
    }

    /**
     * Holt den Content für die angegebene Sprache
     */
    private static function getContent(Snippet $snippet, int $clangId): string
    {
        if (!$snippet->isMultilang()) {
            return $snippet->getContent();
        }

        // Mehrsprachigen Content aus Translation-Tabelle holen
        $translation = SnippetRepository::getTranslation($snippet->getId(), $clangId);

        if (null !== $translation) {
            return $translation;
        }

        // Fallback auf Standard-Content
        return $snippet->getContent();
    }

    /**
     * Rendert ein PHP-Snippet (nur für Admins!)
     *
     * @param array<string, mixed> $params
     */
    private static function renderPhp(Snippet $snippet, string $code, array $params): string
    {
        if (!PermissionService::canEditPhp()) {
            \rex_logger::factory()->log(
                'warning',
                'Attempt to execute PHP snippet without permission: ' . $snippet->getKey()
            );
            return '';
        }

        // Audit-Log
        self::logExecution($snippet);

        // PHP-Code in isoliertem Kontext ausführen
        \rex_response::cleanOutputBuffers();
        ob_start();

        try {
            // Fragment-Kontext für Snippet
            $fragment = new \rex_fragment();
            $fragment->setVar('params', $params, false);
            $fragment->setVar('snippet', $snippet, false);

            // Code ausführen
            // Variablen für den Snippet-Code verfügbar machen
            $SNIPPET_PARAMS = $params;
            $SNIPPET_KEY = $snippet->getKey();

            // eval mit PHP-Tags
            $evalResult = eval('?>' . $code);

            if (false === $evalResult) {
                throw new \rex_exception('PHP parse error in snippet: ' . $snippet->getKey());
            }

            $output = ob_get_clean();

            return false !== $output ? $output : '';
        } catch (\Throwable $e) {
            ob_end_clean();

            \rex_logger::logException($e);

            if (\rex::isDebugMode()) {
                return '<!-- PHP Snippet Error (' . \rex_escape($snippet->getKey()) . '): '
                    . \rex_escape($e->getMessage()) . ' -->';
            }

            return '';
        }
    }

    /**
     * Rendert ein Template-Snippet (HTML/Text)
     *
     * @param array<string, mixed> $params
     */
    private static function renderTemplate(string $content, array $params): string
    {
        // Einfache Parameter-Ersetzung für HTML/Text
        foreach ($params as $key => $value) {
            $content = str_replace('{' . $key . '}', \rex_escape($value), $content);
        }

        return $content;
    }

    /**
     * Rendert ein Template-Snippet direkt (für ReplacementService-Optimierung)
     *
     * @param array<string, mixed> $params
     */
    public static function renderTemplateDirect(string $content, array $params): string
    {
        return self::renderTemplate($content, $params);
    }

    /**
     * Rendert ein PHP-Snippet direkt (für ReplacementService-Optimierung)
     *
     * @param array<string, mixed> $params
     */
    public static function renderPhpDirect(Snippet $snippet, string $code, array $params): string
    {
        return self::renderPhp($snippet, $code, $params);
    }

    /**
     * Loggt die Ausführung eines PHP-Snippets
     */
    private static function logExecution(Snippet $snippet): void
    {
        $user = \rex::getUser();
        $userLogin = null !== $user ? $user->getLogin() : 'system';

        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('snippets_log'));
        $sql->setValues([
            'snippet_id' => $snippet->getId(),
            'user_login' => $userLogin,
            'action' => 'execute',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $sql->insert();
    }
}
