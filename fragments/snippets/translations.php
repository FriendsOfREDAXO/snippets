<?php
/**
 * Snippet Übersetzungen Fragment
 *
 * @var FriendsOfREDAXO\Snippets\Domain\Snippet $snippet
 * @var rex_csrf_token $csrf_token
 */

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;

$snippet = $this->getVar('snippet');
$csrfToken = $this->getVar('csrf_token');

// Sprachen laden
$clangs = rex_clang::getAll();
$defaultClangId = rex_clang::getStartId();

// Übersetzungen speichern
$func = rex_request::request('func', 'string');
if ('save_translations' === $func) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $sql = rex_sql::factory();
        $errors = [];
        
        foreach ($clangs as $clang) {
            if ($clang->getId() === $defaultClangId) {
                continue; // Standardsprache überspringen
            }
            
            $content = rex_request::post('translation_' . $clang->getId(), 'string', '');
            
            // Prüfen ob bereits Übersetzung existiert
            $sql->setQuery(
                'SELECT id FROM ' . rex::getTable('snippets_translation') . 
                ' WHERE snippet_id = ? AND clang_id = ?',
                [$snippet->getId(), $clang->getId()]
            );
            
            if ($sql->getRows() > 0) {
                // Update
                $sql->setTable(rex::getTable('snippets_translation'));
                $sql->setWhere('snippet_id = :snippet_id AND clang_id = :clang_id', [
                    'snippet_id' => $snippet->getId(),
                    'clang_id' => $clang->getId()
                ]);
                $sql->setValue('content', $content);
                $sql->setValue('updatedate', date('Y-m-d H:i:s'));
                $sql->update();
            } else {
                // Insert
                $sql->setTable(rex::getTable('snippets_translation'));
                $sql->setValue('snippet_id', $snippet->getId());
                $sql->setValue('clang_id', $clang->getId());
                $sql->setValue('content', $content);
                $sql->setValue('createdate', date('Y-m-d H:i:s'));
                $sql->setValue('updatedate', date('Y-m-d H:i:s'));
                $sql->insert();
            }
        }
        
        echo rex_view::success(rex_i18n::msg('snippets_translations_saved'));
    }
}
?>

<div class="rex-addon-output">
    <h2 class="rex-hl2"><?= rex_i18n::msg('snippets_translations_title') ?></h2>
    
    <div class="rex-addon-content">
        <p class="help-block">
            <?= rex_i18n::msg('snippets_translations_help') ?>
        </p>
        
        <form action="<?= rex_url::currentBackendPage(['func' => 'save_translations', 'id' => $snippet->getId(), 'tab' => 'translations']) ?>" method="post">
            <?= $csrfToken->getHiddenField() ?>
            
            <fieldset>
                <legend><?= rex_i18n::msg('snippets_translations_legend') ?></legend>
                
                <!-- Hinweis Standard-Inhalt -->
                <div class="alert alert-info">
                    <strong><?= rex_i18n::msg('snippets_translations_default') ?>:</strong>
                    <code><?= rex_escape(substr($snippet->getContent(), 0, 100)) ?><?= strlen($snippet->getContent()) > 100 ? '...' : '' ?></code>
                </div>
                
                <?php foreach ($clangs as $clang): ?>
                    <?php if ($clang->getId() === $defaultClangId): continue; endif; ?>
                    
                    <?php
                    // Übersetzung laden
                    $translation = SnippetRepository::getTranslation($snippet->getId(), $clang->getId());
                    ?>
                    
                    <div class="form-group">
                        <label for="translation_<?= $clang->getId() ?>">
                            <?= rex_escape($clang->getName()) ?> (<?= rex_escape($clang->getCode()) ?>)
                        </label>
                        
                        <div class="rex-code-editor">
                            <textarea 
                                class="form-control rex-code" 
                                id="translation_<?= $clang->getId() ?>" 
                                name="translation_<?= $clang->getId() ?>" 
                                rows="<?= 'php' === $snippet->getContentType() ? '15' : '10' ?>"
                                <?php if ('php' === $snippet->getContentType()): ?>
                                data-codemirror-mode="application/x-httpd-php"
                                <?php else: ?>
                                data-codemirror-mode="text/html"
                                <?php endif; ?>
                            ><?= rex_escape($translation ?? '') ?></textarea>
                        </div>
                        
                        <p class="help-block">
                            <?php if ($translation): ?>
                                <?= rex_i18n::msg('snippets_translations_has_translation') ?>
                            <?php else: ?>
                                <?= rex_i18n::msg('snippets_translations_no_translation') ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            
            <footer class="panel-footer">
                <div class="rex-form-panel-footer">
                    <div class="btn-toolbar">
                        <button type="submit" class="btn btn-save rex-form-aligned">
                            <i class="rex-icon rex-icon-save"></i> <?= rex_i18n::msg('snippets_save') ?>
                        </button>
                        <a href="<?= rex_url::currentBackendPage(['func' => 'edit', 'id' => $snippet->getId()]) ?>" class="btn btn-abort">
                            <i class="rex-icon rex-icon-back"></i> <?= rex_i18n::msg('snippets_back_to_main') ?>
                        </a>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>

<script>
// CodeMirror für alle Textareas
jQuery(function($) {
    $('.rex-code').each(function() {
        var textarea = this;
        var mode = $(textarea).data('codemirror-mode') || 'text/html';
        
        if (typeof CodeMirror !== 'undefined') {
            CodeMirror.fromTextArea(textarea, {
                lineNumbers: true,
                mode: mode,
                indentUnit: 4,
                indentWithTabs: false,
                theme: 'default'
            });
        }
    });
});
</script>
