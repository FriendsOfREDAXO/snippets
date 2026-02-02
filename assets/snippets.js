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

    // Success-Feedback anzeigen
    function showCopySuccess($btn) {
        var originalHtml = $btn.html();
        var originalClass = $btn.attr('class');
        
        $btn
            .removeClass('btn-default')
            .addClass('btn-success')
            .html('<i class="rex-icon fa-check"></i>');
        
        setTimeout(function() {
            $btn
                .attr('class', originalClass)
                .html(originalHtml);
        }, 1500);
    }

    // Confirm-Dialoge
    $(document).on('click', '[data-confirm]', function(e) {
        var message = $(this).data('confirm');
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });

})(jQuery);
