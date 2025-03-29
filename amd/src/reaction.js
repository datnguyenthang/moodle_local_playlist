define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    var init = function() {
        // Initialize event listeners
        initEventReactionListeners();
    };

    // Function to initialize event listeners for reactions
    function initEventReactionListeners() {
        // Use event delegation for dynamically added elements
        $('body').on('click', '.likes a', function(e) {
            e.preventDefault();
            var $link = $(this);
            var reaction = $link.data('like');
            var commentid = $link.data('commentid');

            ajax.call([{
                methodname: 'local_playlist_set_reaction',
                args: {
                    reaction: reaction,
                    commentid: commentid
                }
            }])[0].done(function(response) {
                // Update the likes and dislikes counts dynamically
                templates.render('local_playlist/details/_reaction', response)
                    .done(function(html) {
                        $('#likes-' + commentid).html(html);
                        initEventReactionListeners();
                    });
            }).fail(function(error) {
                console.error('Error liking/disliking comment:', error);
            });
        });
    }

    return {
        init: init
    };
});
