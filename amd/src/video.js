define(['jquery', 'core/ajax', 'core/templates'], function($, ajax, templates) {
    var init = function() {
        // Wait for DOM to be ready
        $(document).ready(function() {
            // Initialize video player for direct video files
            initializeDirectVideo();

            // Handle embedded videos (YouTube, Vimeo, etc.)
            initializeEmbeddedVideo();
        });
    };

    var initializeDirectVideo = function() {
        // Initialize video.js for direct video files if present
        if ($('video#playlist-video').length > 0) {
            require(['local_playlist/video-js'], function(videojs) {
                var player = videojs('playlist-video');
                player.ready(function() {
                    console.log('Video.js player is ready');
                });
            });
        }
    };

    var initializeEmbeddedVideo = function() {
        // Handle embedded videos (YouTube, Vimeo, etc.)
        $('.video-container iframe').each(function() {
            var $iframe = $(this);
            var src = $iframe.attr('src');
            if (src) {
                // Add autoplay or other parameters if needed
                if (!src.includes('autoplay')) {
                    src += (src.includes('?') ? '&' : '?') + 'autoplay=1';
                    $iframe.attr('src', src);
                }
                console.log('Embedded video initialized:', src);
            }
        });
    };

    return {
        init: init
    };
});
