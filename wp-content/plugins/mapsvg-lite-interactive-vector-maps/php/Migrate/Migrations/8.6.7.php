<?php

namespace MapSVG;

/**
 */
return function () {

    $repo = RepositoryFactory::get("map");
    $maps = $repo->find();

    // Add new option allowed_shortcodes with empty array    

    $allowedShortcodes = [];

    // Add all shortcodes from all maps to the allowed shortcodes
    if ($maps["items"]) {
        foreach ($maps["items"] as $map) {
            $allowedShortcodesNew = $map->getShortcodeNamesFromTemplates();
            $allowedShortcodes = array_merge($allowedShortcodes, $allowedShortcodesNew);
        }
    }

    // Remove duplicates
    $allowedShortcodes = array_unique($allowedShortcodes);

    // Save allowed shortcodes to the database
    add_action('init', function ()  use ($allowedShortcodes) {
        // Safe to call current_user_can(), wp_get_current_user(), etc. here
        Options::set('allowed_shortcodes', $allowedShortcodes);
    });
};
