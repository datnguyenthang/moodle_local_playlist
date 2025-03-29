define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    return {
        init: function() {
            $(document).ready(function() {
                let field = $('.share-field');
                let input = $('.share-link');
                let copyBtn = $('.button-share');

                copyBtn.on('click', function() {
                    input.select();
                    if (document.execCommand("copy")) {
                        field.addClass('active');
                        copyBtn.text('Copied');
                        setTimeout(function() {
                            field.removeClass('active');
                            copyBtn.text('Copy');
                        }, 3500);
                    }
                });
            });
        }
    };
});
