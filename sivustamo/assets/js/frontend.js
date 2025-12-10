/**
 * Sivustamo Frontend Scripts
 */

(function($) {
    'use strict';

    // Feedback functionality
    var Feedback = {
        init: function() {
            this.$container = $('.sivustamo-feedback');
            if (!this.$container.length) return;

            this.ohjeId = this.$container.data('ohje-id');
            this.selectedThumbs = null;
            this.selectedStars = 0;

            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Thumbs buttons
            this.$container.on('click', '.sivustamo-feedback-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var thumbs = $btn.data('thumbs');

                // Toggle active state
                self.$container.find('.sivustamo-feedback-btn').removeClass('active');
                $btn.addClass('active');
                self.selectedThumbs = thumbs;

                // Show extended form
                self.$container.find('.sivustamo-feedback-extended').slideDown(200);
            });

            // Star rating
            this.$container.on('click', '.sivustamo-star', function(e) {
                e.preventDefault();
                var star = $(this).data('star');
                self.selectedStars = star;

                // Update visual state
                self.$container.find('.sivustamo-star').each(function() {
                    var $star = $(this);
                    if ($star.data('star') <= star) {
                        $star.addClass('active');
                    } else {
                        $star.removeClass('active');
                    }
                });
            });

            // Star hover effect
            this.$container.on('mouseenter', '.sivustamo-star', function() {
                var hoverStar = $(this).data('star');
                self.$container.find('.sivustamo-star').each(function() {
                    var $star = $(this);
                    if ($star.data('star') <= hoverStar) {
                        $star.addClass('hover');
                    } else {
                        $star.removeClass('hover');
                    }
                });
            });

            this.$container.on('mouseleave', '.sivustamo-stars', function() {
                self.$container.find('.sivustamo-star').removeClass('hover');
            });

            // Submit feedback
            this.$container.on('click', '.sivustamo-feedback-submit', function(e) {
                e.preventDefault();
                self.submit();
            });
        },

        submit: function() {
            var self = this;

            if (!this.selectedThumbs) {
                return;
            }

            var $btn = this.$container.find('.sivustamo-feedback-submit');
            var comment = this.$container.find('#sivustamo-comment').val();

            $btn.prop('disabled', true).text('...');

            $.ajax({
                url: sivustamoFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sivustamo_submit_feedback',
                    nonce: sivustamoFrontend.nonce,
                    ohje_id: this.ohjeId,
                    thumbs: this.selectedThumbs,
                    stars: this.selectedStars,
                    comment: comment
                },
                success: function(response) {
                    if (response.success) {
                        // Show thanks message
                        self.$container.find('.sivustamo-feedback-buttons, .sivustamo-feedback-extended').hide();
                        self.$container.find('.sivustamo-feedback-thanks').show();
                    } else {
                        alert(sivustamoFrontend.strings.feedbackError);
                        $btn.prop('disabled', false).text(sivustamoFrontend.strings.submit || 'Lähetä palaute');
                    }
                },
                error: function() {
                    alert(sivustamoFrontend.strings.feedbackError);
                    $btn.prop('disabled', false).text(sivustamoFrontend.strings.submit || 'Lähetä palaute');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        Feedback.init();
    });

})(jQuery);
