/**
 * Snippets AddOn – String-Übersetzungen (Translations)
 *
 * Click-to-Edit Inline-Bearbeitung, AJAX-Speichern, DeepL-Integration,
 * Batch-Übersetzung. Nutzt REDAXO's eingebautes PJAX für Navigation.
 *
 * @package redaxo\snippets
 */

(function ($) {
    'use strict';

    var API_URL = window.location.pathname;

    // =========================================================================
    // Initialisierung (auch nach PJAX-Reload)
    // =========================================================================
    $(document).on('rex:ready', function () {
        initTranslations();
    });

    function initTranslations() {
        var $container = $('#snippets-translations');
        if ($container.length === 0) {
            return;
        }

        // Change-Tracking für Inputs
        $container.find('.snippets-tstr-value').each(function () {
            $(this).data('original', $(this).val());
        });

        // Stelle sicher, dass alle Edit-Felder geschlossen sind
        $container.find('.snippets-tstr-edit').hide();
        $container.find('.snippets-tstr-text').show();

        updateSaveButtons();
    }

    // =========================================================================
    // Click-to-Edit: Text anklicken → Input anzeigen
    // =========================================================================
    $(document).on('click', '.snippets-tstr-text', function (e) {
        e.stopPropagation();
        var $cell = $(this).closest('.snippets-tstr-cell');
        openEditor($cell);
    });

    function openEditor($cell) {
        // Andere offene Editoren in gleicher Zeile nicht schließen
        $cell.find('.snippets-tstr-text').hide();
        $cell.find('.snippets-tstr-edit').show();
        $cell.addClass('snippets-tstr-cell-editing');
        $cell.find('.snippets-tstr-value').focus();
    }

    function closeEditor($cell, updateText) {
        var $input = $cell.find('.snippets-tstr-value');
        var $text = $cell.find('.snippets-tstr-text');

        if (updateText !== false) {
            var val = $input.val();
            if (val && val.trim() !== '') {
                $text.text(val).removeClass('snippets-tstr-text-empty');
            } else {
                $text.text('–').addClass('snippets-tstr-text-empty');
            }
        }

        $cell.find('.snippets-tstr-edit').hide();
        $cell.removeClass('snippets-tstr-cell-editing');
        $text.show();
    }

    // Blur: Editor schließen (mit Verzögerung, damit Klicks auf DeepL-Button funktionieren)
    $(document).on('blur', '.snippets-tstr-value', function () {
        var $input = $(this);
        var $cell = $input.closest('.snippets-tstr-cell');

        setTimeout(function () {
            // Nur schließen, wenn innerhalb der Zelle kein Element mehr fokussiert ist
            if ($cell.find(':focus').length === 0) {
                closeEditor($cell);
            }
        }, 200);
    });

    // =========================================================================
    // Change-Tracking: Erkenne geänderte Felder
    // =========================================================================
    $(document).on('input', '.snippets-tstr-value', function () {
        var $input = $(this);
        var isChanged = $input.val() !== $input.data('original');
        $input.toggleClass('snippets-tstr-changed', isChanged);

        var stringId = $input.data('string-id');
        updateRowSaveButton(stringId);
        updateSaveButtons();
    });

    function updateRowSaveButton(stringId) {
        var $row = $('.snippets-tstr-row[data-string-id="' + stringId + '"]');
        var hasChanges = $row.find('.snippets-tstr-changed').length > 0;
        $row.find('.snippets-tstr-save-row').prop('disabled', !hasChanges);
    }

    function updateSaveButtons() {
        var hasAnyChanges = $('.snippets-tstr-changed').length > 0;
        $('.snippets-tstr-save-all').prop('disabled', !hasAnyChanges);
    }

    // =========================================================================
    // Einzelne Zeile speichern
    // =========================================================================
    $(document).on('click', '.snippets-tstr-save-row', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var stringId = $btn.data('string-id');
        saveRow(stringId, $btn);
    });

    function saveRow(stringId, $btn) {
        var $row = $('.snippets-tstr-row[data-string-id="' + stringId + '"]');
        var values = {};

        $row.find('.snippets-tstr-value').each(function () {
            var $input = $(this);
            values[$input.data('clang-id')] = $input.val();
        });

        $btn.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');

        $.ajax({
            url: API_URL,
            method: 'POST',
            dataType: 'json',
            data: {
                'rex-api-call': 'snippets_translations',
                action: 'save_all',
                string_id: stringId,
                values: values
            }
        })
            .done(function (data) {
                if (data.success) {
                    // Original-Werte und Texte aktualisieren
                    $row.find('.snippets-tstr-value').each(function () {
                        var $input = $(this);
                        $input.data('original', $input.val()).removeClass('snippets-tstr-changed');
                        // Text-Element aktualisieren
                        var $cell = $input.closest('.snippets-tstr-cell');
                        closeEditor($cell, true);
                    });
                    showRowFeedback($row, 'success');
                    updateRowSaveButton(stringId);
                    updateSaveButtons();
                } else {
                    showRowFeedback($row, 'error', data.error || 'Fehler');
                }
            })
            .fail(function () {
                showRowFeedback($row, 'error', 'Netzwerkfehler');
            })
            .always(function () {
                $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            });
    }

    // =========================================================================
    // Alle geänderten Zeilen speichern
    // =========================================================================
    $(document).on('click', '.snippets-tstr-save-all', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true);

        var changedRows = [];
        $('.snippets-tstr-changed').each(function () {
            var id = $(this).data('string-id');
            if (changedRows.indexOf(id) === -1) {
                changedRows.push(id);
            }
        });

        var completed = 0;
        var total = changedRows.length;

        changedRows.forEach(function (stringId) {
            var $rowBtn = $('.snippets-tstr-save-row[data-string-id="' + stringId + '"]');
            saveRow(stringId, $rowBtn);
            completed++;
            if (completed >= total) {
                $btn.prop('disabled', true);
            }
        });
    });

    // =========================================================================
    // Kategorie ändern (Icon-Trigger → natives Dropdown)
    // =========================================================================

    // Klick auf Trigger → Dropdown ein-/ausblenden
    $(document).on('click', '.snippets-tstr-category-trigger[data-string-id]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var stringId = $(this).attr('data-string-id');
        var $dropdown = $('.snippets-tstr-category-dropdown[data-string-id="' + stringId + '"]');

        // Alle anderen Dropdowns schließen
        $('.snippets-tstr-category-dropdown').not($dropdown).slideUp(100);

        $dropdown.slideToggle(100, function () {
            if ($dropdown.is(':visible')) {
                $dropdown.find('select').focus();
            }
        });
    });

    // Klick außerhalb → Dropdown schließen
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.snippets-tstr-category-trigger, .snippets-tstr-category-dropdown').length) {
            $('.snippets-tstr-category-dropdown').slideUp(100);
        }
    });

    // Kategorie geändert → AJAX speichern + Icon aktualisieren
    $(document).on('change', '.snippets-tstr-category-select', function () {
        var $select = $(this);
        var stringId = parseInt($select.attr('data-string-id'), 10);
        var categoryId = parseInt($select.val(), 10);
        var originalVal = parseInt($select.attr('data-original'), 10) || 0;

        if (categoryId === originalVal) {
            $('.snippets-tstr-category-dropdown').slideUp(100);
            return;
        }

        var $trigger = $('.snippets-tstr-category-trigger[data-string-id="' + stringId + '"]');
        var $icon = $trigger.find('.snippets-tstr-category-icon');

        // Icon + Attribut sofort aktualisieren
        var selectedOption = $select.find('option:selected');
        var newIcon = selectedOption.attr('data-icon') || 'fa-folder-o';
        var newName = selectedOption.text().trim();
        $icon.attr('class', 'rex-icon ' + newIcon + ' snippets-tstr-category-icon');
        $trigger.attr('title', newName);
        $trigger.attr('data-has-category', categoryId > 0 ? '1' : '0');

        // Dropdown schließen
        $('.snippets-tstr-category-dropdown').slideUp(100);

        $.ajax({
            url: API_URL,
            method: 'POST',
            dataType: 'json',
            data: {
                'rex-api-call': 'snippets_translations',
                action: 'update_category',
                string_id: stringId,
                category_id: categoryId
            }
        })
            .done(function (data) {
                if (data && data.success) {
                    $select.attr('data-original', categoryId);
                    var $row = $select.closest('.snippets-tstr-row');
                    showRowFeedback($row, 'success');
                }
            })
            .fail(function () {
                // Zurücksetzen bei Fehler
                $select.val(originalVal);
                var origOption = $select.find('option[value="' + originalVal + '"]');
                var origIcon = origOption.attr('data-icon') || 'fa-folder-o';
                $icon.attr('class', 'rex-icon ' + origIcon + ' snippets-tstr-category-icon');
                $trigger.attr('data-has-category', originalVal > 0 ? '1' : '0');
            });
    });

    // =========================================================================
    // Key Inline-Edit: Doppelklick auf Placeholder → Input anzeigen
    // =========================================================================
    $(document).on('dblclick', '.snippets-tstr-placeholder', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $code = $(this);
        var $td = $code.closest('td');
        var $wrapper = $td.find('.snippets-tstr-key-wrapper');
        var $editBox = $td.find('.snippets-tstr-key-edit');

        if ($editBox.length === 0) return;

        $wrapper.hide();
        $editBox.show();
        $editBox.find('.snippets-tstr-key-input').focus().select();
    });

    // Key speichern
    $(document).on('click', '.snippets-tstr-key-save', function () {
        var $editBox = $(this).closest('.snippets-tstr-key-edit');
        saveKey($editBox);
    });

    // Key abbrechen
    $(document).on('click', '.snippets-tstr-key-cancel', function () {
        var $editBox = $(this).closest('.snippets-tstr-key-edit');
        cancelKeyEdit($editBox);
    });

    // Enter → speichern, Escape → abbrechen
    $(document).on('keydown', '.snippets-tstr-key-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveKey($(this).closest('.snippets-tstr-key-edit'));
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelKeyEdit($(this).closest('.snippets-tstr-key-edit'));
        }
    });

    function saveKey($editBox) {
        var $input = $editBox.find('.snippets-tstr-key-input');
        var newKey = $.trim($input.val());
        var originalKey = $input.attr('data-original');
        var stringId = parseInt($editBox.attr('data-string-id'), 10);
        var $td = $editBox.closest('td');

        if (newKey === originalKey) {
            cancelKeyEdit($editBox);
            return;
        }

        if (!newKey || !/^[a-zA-Z0-9_\-\.]+$/.test(newKey)) {
            showInputFeedback($input, 'error');
            return;
        }

        $input.prop('disabled', true);

        $.ajax({
            url: API_URL,
            method: 'POST',
            dataType: 'json',
            data: {
                'rex-api-call': 'snippets_translations',
                action: 'update_key',
                string_id: stringId,
                new_key: newKey
            }
        })
            .done(function (data) {
                if (data && data.success) {
                    // Code + Copy-Button aktualisieren
                    var $code = $td.find('.snippets-tstr-placeholder');
                    $code.html('&#91;&#91; ' + $('<span>').text(newKey).html() + ' &#93;&#93;');
                    $code.attr('data-key', newKey);
                    $td.find('.snippets-tstr-copy').attr('data-clipboard-text', '[[ ' + newKey + ' ]]');
                    $input.attr('data-original', newKey);

                    var $row = $editBox.closest('.snippets-tstr-row');
                    cancelKeyEdit($editBox);
                    showRowFeedback($row, 'success');
                } else {
                    showInputFeedback($input, 'error');
                    if (data && data.error) {
                        alert(data.error);
                    }
                }
            })
            .fail(function () {
                showInputFeedback($input, 'error');
            })
            .always(function () {
                $input.prop('disabled', false);
            });
    }

    function cancelKeyEdit($editBox) {
        var $td = $editBox.closest('td');
        var $input = $editBox.find('.snippets-tstr-key-input');
        $input.val($input.attr('data-original'));
        $editBox.hide();
        $td.find('.snippets-tstr-key-wrapper').show();
    }

    // =========================================================================
    // DeepL-Übersetzung (einzeln)
    // =========================================================================
    $(document).on('click', '.snippets-tstr-deepl', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var stringId = parseInt($btn.attr('data-string-id'), 10);
        var targetClangId = parseInt($btn.attr('data-clang-id'), 10);
        var targetClangCode = String($btn.attr('data-clang-code') || '');

        // Quelltext finden: Ersten nicht-leeren Wert der Zeile nehmen
        var $row = $btn.closest('.snippets-tstr-row');
        var sourceText = '';
        var sourceClangCode = '';

        $row.find('input.snippets-tstr-value').each(function () {
            if (sourceText) return;
            var val = $(this).val() || '';
            if (val.trim() !== '') {
                sourceText = val.trim();
                var $parentCell = $(this).closest('[data-clang-code]');
                if ($parentCell.length) {
                    sourceClangCode = String($parentCell.attr('data-clang-code'));
                }
            }
        });

        if (!sourceText) {
            alert('Kein Quelltext vorhanden. Bitte zuerst einen Text in einer Sprache eingeben.');
            return;
        }

        var $targetInput = $row.find('input.snippets-tstr-value[data-clang-id="' + targetClangId + '"]');

        // Loading-State
        $btn.prop('disabled', true).find('i').removeClass('fa-language').addClass('fa-spinner fa-spin');

        // Translate + Save in einem API-Call
        $.ajax({
            url: API_URL,
            method: 'POST',
            dataType: 'json',
            data: {
                'rex-api-call': 'snippets_translations',
                action: 'translate',
                text: sourceText,
                target_lang: targetClangCode,
                source_lang: sourceClangCode,
                string_id: stringId,
                clang_id: targetClangId
            }
        })
            .done(function (data) {
                if (data && data.success && data.translated) {
                    // Input aktualisieren
                    $targetInput.val(data.translated).trigger('input');
                    $targetInput.data('original', data.translated).removeClass('snippets-tstr-changed');

                    // Editor schließen und Text-Anzeige aktualisieren
                    var $cell = $targetInput.closest('.snippets-tstr-cell');
                    closeEditor($cell, true);
                    showRowFeedback($row, 'success');
                    updateRowSaveButton(stringId);
                    updateSaveButtons();
                } else {
                    showInputFeedback($targetInput, 'error');
                    if (data && data.error) {
                        console.error('DeepL:', data.error);
                    }
                }
            })
            .fail(function (xhr) {
                showInputFeedback($targetInput, 'error');
                console.error('DeepL request failed:', xhr.status, xhr.statusText);
            })
            .always(function () {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-language');
            });
    });

    // =========================================================================
    // Batch-Übersetzung: Serverseitig alle Strings einer Zielsprache übersetzen
    // =========================================================================
    $(document).on('click', '.snippets-tstr-batch-translate', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.snippets-tstr-batch-form');
        var $select = $form.find('select.snippets-tstr-batch-target');
        var targetClangId = parseInt($select.val(), 10);
        var emptyOnly = $form.find('.snippets-tstr-batch-empty-only').is(':checked') ? 1 : 0;

        if (!targetClangId) {
            return;
        }

        // UI: Button deaktivieren, Fortschritt anzeigen
        $btn.prop('disabled', true);
        var $progress = $form.find('.snippets-tstr-batch-progress');
        var $progressFill = $progress.find('.snippets-tstr-batch-progress-fill');
        var $progressText = $progress.find('.snippets-tstr-batch-progress-text');
        $progress.show();
        $progressFill.css('width', '100%').addClass('snippets-tstr-progress-animated');
        $progressText.text('Übersetze…');

        $.ajax({
            url: API_URL,
            method: 'POST',
            dataType: 'json',
            timeout: 300000, // 5 Minuten Timeout für viele Strings
            data: {
                'rex-api-call': 'snippets_translations',
                action: 'batch_translate',
                target_clang_id: targetClangId,
                empty_only: emptyOnly
            }
        })
            .done(function (data) {
                if (data && data.success) {
                    var msg = data.translated + ' übersetzt';
                    if (data.skipped > 0) {
                        msg += ', ' + data.skipped + ' übersprungen';
                    }
                    if (data.errors > 0) {
                        msg += ', ' + data.errors + ' Fehler';
                    }
                    $progressFill.removeClass('snippets-tstr-progress-animated');
                    $progressText.text(msg + ' – Seite wird aktualisiert…');

                    // Seite per PJAX neu laden
                    setTimeout(function () {
                        if (typeof $.pjax !== 'undefined' && typeof $.pjax.reload === 'function') {
                            $.pjax.reload('#rex-js-page-container', {timeout: 10000});
                        } else {
                            window.location.reload();
                        }
                    }, 800);
                } else {
                    $progressFill.removeClass('snippets-tstr-progress-animated');
                    $progressText.text('Fehler: ' + (data && data.error ? data.error : 'Unbekannt'));
                    $btn.prop('disabled', false);
                    setTimeout(function () { $progress.fadeOut(); }, 3000);
                }
            })
            .fail(function (xhr) {
                $progressFill.removeClass('snippets-tstr-progress-animated');
                $progressText.text('Netzwerkfehler: ' + xhr.statusText);
                $btn.prop('disabled', false);
                setTimeout(function () { $progress.fadeOut(); }, 3000);
            });
    });

    // =========================================================================
    // Copy-to-Clipboard
    // =========================================================================
    $(document).on('click', '.snippets-tstr-copy', function (e) {
        e.preventDefault();
        var text = $(this).data('clipboard-text');
        var $btn = $(this);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                $btn.removeClass('btn-default').addClass('btn-success');
                setTimeout(function () {
                    $btn.removeClass('btn-success').addClass('btn-default');
                }, 1200);
            });
        }
    });

    // =========================================================================
    // Tastatur-Navigation
    // =========================================================================
    $(document).on('keydown', '.snippets-tstr-value', function (e) {
        var $input = $(this);
        var $cell = $input.closest('.snippets-tstr-cell');

        // Escape: Editor schließen ohne zu speichern
        if (e.key === 'Escape') {
            e.preventDefault();
            $input.val($input.data('original')).trigger('input');
            closeEditor($cell, true);
            return;
        }

        // Enter: Speichern und nächste Zeile, gleiche Sprache öffnen
        if (e.key === 'Enter') {
            e.preventDefault();
            var stringId = $input.data('string-id');
            var clangId = $input.data('clang-id');

            // Editor schließen
            closeEditor($cell, true);

            // Zeile speichern wenn geändert
            var $row = $input.closest('.snippets-tstr-row');
            if ($row.find('.snippets-tstr-changed').length > 0) {
                var $btn = $row.find('.snippets-tstr-save-row');
                saveRow(stringId, $btn);
            }

            // Zur nächsten Zeile, gleiche Sprache öffnen
            var $nextRow = $row.next('.snippets-tstr-row');
            if ($nextRow.length) {
                var $nextCell = $nextRow.find('.snippets-tstr-cell[data-clang-id="' + clangId + '"]');
                if ($nextCell.length) {
                    openEditor($nextCell);
                }
            }
        }

        // Tab: Nächstes Feld in der gleichen Zeile öffnen
        if (e.key === 'Tab' && !e.shiftKey) {
            var $cells = $input.closest('.snippets-tstr-row').find('.snippets-tstr-cell');
            var currentIndex = $cells.index($cell);
            if (currentIndex < $cells.length - 1) {
                e.preventDefault();
                closeEditor($cell, true);
                openEditor($cells.eq(currentIndex + 1));
            }
        }

        // Shift+Tab: Vorheriges Feld
        if (e.key === 'Tab' && e.shiftKey) {
            var $cells = $input.closest('.snippets-tstr-row').find('.snippets-tstr-cell');
            var currentIndex = $cells.index($cell);
            if (currentIndex > 0) {
                e.preventDefault();
                closeEditor($cell, true);
                openEditor($cells.eq(currentIndex - 1));
            }
        }
    });

    // =========================================================================
    // Löschen bestätigen
    // =========================================================================
    $(document).on('click', '[data-confirm]', function (e) {
        var msg = $(this).data('confirm');
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    // =========================================================================
    // Feedback-Hilfsfunktionen
    // =========================================================================
    function showRowFeedback($row, type) {
        var cls = type === 'success' ? 'snippets-tstr-flash-success' : 'snippets-tstr-flash-error';
        $row.addClass(cls);
        setTimeout(function () {
            $row.removeClass(cls);
        }, 1200);
    }

    function showInputFeedback($input, type) {
        var cls = type === 'success' ? 'snippets-tstr-input-success' : 'snippets-tstr-input-error';
        $input.addClass(cls);
        setTimeout(function () {
            $input.removeClass(cls);
        }, 1500);
    }

})(jQuery);
