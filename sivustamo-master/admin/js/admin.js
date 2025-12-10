/**
 * Sivustamo Master Admin Scripts
 */

(function($) {
    'use strict';

    // Copy to clipboard
    $(document).on('click', '.sivustamo-copy-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var targetId = $btn.data('target');
        var $target = $('#' + targetId);
        var text = $target.text().trim();

        // Copy to clipboard
        navigator.clipboard.writeText(text).then(function() {
            // Show success
            var originalText = $btn.text();
            $btn.text(sivustamoMaster.strings.copied).addClass('copied');

            setTimeout(function() {
                $btn.text(originalText).removeClass('copied');
            }, 2000);
        }).catch(function(err) {
            console.error('Copy failed:', err);

            // Fallback
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();

            var originalText = $btn.text();
            $btn.text(sivustamoMaster.strings.copied).addClass('copied');

            setTimeout(function() {
                $btn.text(originalText).removeClass('copied');
            }, 2000);
        });
    });

    // Regenerate API keys
    $(document).on('click', '#regenerate-keys-btn', function(e) {
        e.preventDefault();

        if (!confirm(sivustamoMaster.strings.confirmDelete)) {
            return;
        }

        var $btn = $(this);
        var postId = $btn.data('post-id');

        $btn.prop('disabled', true).text('...');

        $.ajax({
            url: sivustamoMaster.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sivustamo_regenerate_keys',
                nonce: sivustamoMaster.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    $('#api-key-display').text(response.data.api_key);
                    $('#secret-display').text(response.data.secret);

                    alert('Uudet avaimet generoitu!');
                } else {
                    alert('Virhe: ' + response.data.message);
                }
            },
            error: function() {
                alert('Virhe yhteydessä');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Generoi uudet avaimet');
            }
        });
    });

    // Initialize
    $(document).ready(function() {
        // Add any initialization code here
    });

})(jQuery);
