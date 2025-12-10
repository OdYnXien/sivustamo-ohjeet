/**
 * Sivustamo Admin Scripts
 */

(function($) {
    'use strict';

    // Force sync single ohje
    $(document).on('click', '.sivustamo-sync-reset', function(e) {
        e.preventDefault();

        if (!confirm(sivustamo.strings.confirmSync)) {
            return;
        }

        var $btn = $(this);
        var postId = $btn.data('post-id');

        $btn.prop('disabled', true).text(sivustamo.strings.syncing);

        $.ajax({
            url: sivustamo.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sivustamo_force_sync',
                nonce: sivustamo.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    alert(sivustamo.strings.syncComplete);
                    location.reload();
                } else {
                    alert(sivustamo.strings.syncError + ': ' + response.data.message);
                }
            },
            error: function() {
                alert(sivustamo.strings.syncError);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Synkronoi lähteen kanssa');
            }
        });
    });

})(jQuery);
