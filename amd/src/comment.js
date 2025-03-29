define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    var init = function() {
        // Initialize event listeners initEventCommentListeners
        initEventCommentListeners();

        $('body').on('click', '#submitnewcomment', function(e) {
            e.preventDefault();
            if ($(this).prop('disabled')) {
                return;
            }
            $(this).prop('disabled', true).text('Submitting...');

            submitCommentForm($(this).closest('form'));
            $(this).prop('disabled', false);
        });

        function initEventCommentListeners() {
            // Function to handle delete comment
            $('.delete-comment').on('click', function(e) {
                e.preventDefault();
                var commentid = $(this).data('commentid');

                ajax.call([{
                    methodname: 'local_playlist_delete_comment',
                    args: {
                        commentid: commentid
                    }
                }])[0].done(function(response) {
                    templates.render('local_playlist/details/_comment_list', { comments: response.comments })
                        .done(function(html) {
                            $('#comments-list').html(html);
                            initEventCommentListeners();
                        });
                }).fail(function(error) {
                    console.error('Error deleting comment:', error);
                });
            });

            // Function to show/hide reply form
            $('.btn-replay').on('click', function(e) {
                e.preventDefault();
                var formId = $(this).data('id');
                $('#' + formId).toggle();
            });

            // Function to handle edit comment
            $('.edit-comment').on('click', function(e) {
                e.preventDefault();
                var commentid = $(this).data('commentid');
                $('#comment-msg-' + commentid).toggle();
                $('#edit-comment-form-' + commentid).toggle();
            });
            
            $('body').on('keydown', '.edit-comment-text, .form-comment textarea', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $form = $(this).closest('form').toggle();
                    $form.find('textarea[name="comment"]').prop('disabled', true);

                    setTimeout(function() {
                        $form.data('processing', false); // Allow subsequent keypresses
                    }, 1000);

                    submitCommentForm($form);
                    $form.find('textarea[name="comment"]').val('');
                }
            });
        }
        
        function submitCommentForm($form) {
            var itemid = $form.find('input[name="itemid"]').val();
            var replyid = $form.find('input[name="replyid"]').val();
            var commentid = $form.find('input[name="commentid"]').val();
            var text = $form.find('textarea[name="comment"], textarea[name="editcomment"]').val();

            if ($.trim(text) === '') {
                return;
            }

            ajax.call([{
                methodname: 'local_playlist_set_comment',
                args: {
                    itemid: itemid,
                    replyid: replyid,
                    commentid: commentid,
                    text: text
                }
            }])[0].done(function(response) {
                // Render the new template
                templates.render('local_playlist/details/_comment_list', { comments: response.comments })
                    .done(function(html) {
                        $('#comments-list').html(html);
                        initEventCommentListeners();
                    });
            }).fail(function(ex) {
                console.error(ex);
            });
        }
    };

    return {
        init: init
    };
});
