<?php
add_filter('login_color_palette', function () {
    return [
        'brand'    => '#0073aa',
        'trim'     => '#181818',
        'trim-alt' => '#282828',
    ];
});

add_filter('login_headertext', function () {
    return get_bloginfo('name');
});
