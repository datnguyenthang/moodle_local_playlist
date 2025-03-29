define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    return {
        init: function(default_learningspace) {
            const loadContents = (learningspace = default_learningspace, type = '', page = 0, limit = 12) => {
                $('#allcontent-loading-icon').show();
                $('#content-container').hide();
                var search_string =  $('.input-search').val();

                ajax.call([{
                    methodname: 'local_playlist_get_allcontents',
                    args: {
                        learningspace : learningspace,
                        search : search_string,
                        type : type,
                    }
                }])[0].done(function(response) {
                    // Render the courses.
                    templates.render('local_playlist/homecontent', { allcontents: response.contents })
                        .done(function(html) {
                            $('#allcontent-container').html(html).show();
                            $('#allcontent-loading-icon').hide();
                        });

                }).fail(function(ex) {
                    console.error(ex);
                    $('#allcontent-loading-icon').hide(); // Hide loading icon on error
                    $('#courses-container').show();
                });
            };
            // Initial load.
            loadContents();

            // Event listener for pagination links.
            /*
            $(document).on('click', '#pagination-container a.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                loadContents(page);
            });
            */
            // search function
            $('#btn-playlist-search').on('click', function() {
                var learningspace_id = $('.learningspace_active').data('learningspaceid');
                loadContents(learningspace_id);
            });
            $('#playlist-input-search').on('keypress', function(e) { 
                if (e.which === 13) {
                    var learningspace_id = $('.learningspace_active').data('learningspaceid');
                    loadContents(learningspace_id);
                }
            });

            // filter on items
            function applyFilter() {
                var learningspace_id = $('.learningspace_active').data('learningspaceid');
                
                // Gather filter values
                var filters = [];
                $('.filter_check:checked').each(function() {
                    filters.push($(this).val());
                });
                
                // Convert filters to string like '1,2,3'
                var filtersString = filters.join(',');
            
                loadContents(learningspace_id, filtersString);
            }
            $('.filter_check').on('change', applyFilter);
            $('#apply-filter').on('click', applyFilter);

            // change color when hover learning space option
            $('.dropdown-item1').hover(
                function() {
                    $(this).find('.big-letter').css({'background-color': '#2eba70', 'color': 'white'});
                }, 
                function() {
                    $(this).find('.big-letter').css({'background-color': '', 'color': ''});
                }
            );

            // event on selected new learning space
            $(".workspace_dropdown .workspaces_menu .dropdown-item1 a").click(function() {
                var learningspace_id = $(this).data('learningspaceid');
                var name = $(this).find('.name').text();
                var first_letter = $(this).find('.big-letter').text();
                var current = $(this).closest('.workspace_dropdown');

                current.find('.dropdown-toggle .name').html(name + ' <span class="caret"></span>');
                current.find('.dropdown-toggle .big-letter').html(first_letter);
                current.find('.learningspace_active').data('learningspaceid', learningspace_id);
                loadContents(learningspace_id);
            });            
            
        }
    };
});
