<?php

namespace MapSVG;

/**
 */
return function () {

    // Add mapsvg to the allowed shortcodes
    add_action('init', function () {
        $allowedShortcodes = Options::get('allowed_shortcodes');
        if (!$allowedShortcodes) {
            $allowedShortcodes = array();
        }
        if (!in_array("mapsvg", $allowedShortcodes)) {
            $allowedShortcodes[] = "mapsvg";
        }
        // Safe to call current_user_can(), wp_get_current_user(), etc. here
        Options::set('allowed_shortcodes', $allowedShortcodes);
    });
};
