/**
 * Snippets AddOn - JavaScript
 *
 * @package redaxo\snippets
 */

(function($) {
    'use strict';

    // Copy-to-Clipboard Funktionalität
    $(document).on('click', '.rex-js-copy-shortcode', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var shortcode = $btn.data('shortcode');
        
        if (!shortcode) {
            return;
        }
        
        // Clipboard API verwenden
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode)
                .then(function() {
                    showCopySuccess($btn);
                })
                .catch(function(err) {
                    console.error('Copy failed:', err);
                    fallbackCopy(shortcode, $btn);
                });
        } else {
            fallbackCopy(shortcode, $btn);
        }
    });

    // Fallback für ältere Browser
    function fallbackCopy(text, $btn) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand('copy');
            showCopySuccess($btn);
        } catch (err) {
            console.error('Fallback copy failed:', err);
        }
        
        $temp.remove();
    }

    // Success-Feedback anzeigen (sichtbar am Button und für Screenreader über eine Live-Region)
    function showCopySuccess($btn) {
        var $status = $('[data-snippets-copy-status]').first();
        if ($status.length) {
            $status.text('');
            setTimeout(function() {
                $status.text($status.data('message') || '');
            }, 50);
        }

        if ($btn.data('snippetsCopyPending')) {
            return;
        }
        $btn.data('snippetsCopyPending', true);

        var originalHtml = $btn.html();
        var originalClass = $btn.attr('class');
        
        $btn
            .removeClass('btn-default')
            .addClass('btn-success')
            .html('<i class="rex-icon fa-check" aria-hidden="true"></i>');
        
        setTimeout(function() {
            $btn
                .attr('class', originalClass)
                .html(originalHtml)
                .data('snippetsCopyPending', false);
        }, 1500);
    }

})(jQuery);
