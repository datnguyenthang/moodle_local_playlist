define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    return {
        init: function() {
            const loadMyContents = () => {
                $('#mycontent-loading-icon').show();
                $('#content-container').hide();

                ajax.call([{
                    methodname: 'local_playlist_get_mycontents',
                    args: {

                    }
                }])[0].done(function(response) {
                    // Render the courses.
                    templates.render('local_playlist/mycontent', { mycontents: response.contents })
                        .done(function(html) {
                            $('#mycontent-container').html(html).show();
                            $('#mycontent-loading-icon').hide();
                        });

                }).fail(function(ex) {
                    console.error(ex);
                    $('#mycontent-loading-icon').hide(); // Hide loading icon on error
                    $('#courses-container').show();
                });
            };
            // Initial load.
            loadMyContents();

            // Event listener for pagination links.
            $(document).on('click', '#pagination-container a.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                loadMyContents(page);
            });
            

            $('#button-search').on('click', function() {
                loadMyContents();
            });

            $('.item').click(function(){
                $('.dropdownTogglerDept').html($(this)[0].innerHTML);
            })
        }
    };
});
