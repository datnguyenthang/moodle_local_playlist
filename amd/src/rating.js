define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    return {
        init: function() {
            var rating = $('.stars');

            var initialize_rating = function(rating) {
                var current_rating = function() {
                    return rating.find('input:checked').val() || 0;
                };
                var draw_rating = function(val) {
                    rating.children('li').each(function(index) {
                        if (index < val) {
                            $(this).find('i').addClass('fa-star').removeClass('fa-star-o fa-star-half-o');
                        } else {
                            $(this).find('i').removeClass('fa-star fa-star-half-o').addClass('fa-star-o');
                        }
                    });
                };

                rating.children('li').mouseover(function() {
                    var val = $(this).find('i').data('val');
                    draw_rating(val);
                });

                rating.children('li').click(function() {
                    var newRating = $(this).find('i').data('val');
                    if (current_rating() == newRating) {
                        return;
                    } else {
                        ajax.call([{
                            methodname: 'local_playlist_set_rating',
                            args: {
                                'itemid': rating.data('itemid'),
                                'rating': newRating
                            }
                        }])[0].done(function(response) {
                            // Render the new template
                            templates.render('local_playlist/details/_rating', { rating: response.rating })
                                .done(function(html) {
                                    $('#rating').html(html);
                                    // Reinitialize rating after rendering new template
                                    initialize_rating($('.stars'));
                                });
                        }).fail(function(ex) {
                            console.error(ex);
                        });
                    }
                });

                // Initialize the current rating view
                //draw_rating(current_rating());
            };

            // Initial call to set up rating interactions
            initialize_rating(rating);
        }
    };
});
