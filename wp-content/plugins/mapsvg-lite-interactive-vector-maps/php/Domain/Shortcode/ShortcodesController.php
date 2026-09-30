<?php

namespace MapSVG;

class ShortcodesController extends Controller
{
    public static function get($request)
    {

        $allowedShortcodes = Options::get('allowed_shortcodes');
        if (!$allowedShortcodes) {
            $allowedShortcodes = array();
        }

        $shortcode = stripslashes(urldecode($request['shortcode']));

        if (empty($shortcode)) {
            return self::render("", 200, "text");
        }

        $shortcodeName = Shortcode::getName($shortcode);

        if (!in_array($shortcodeName, $allowedShortcodes)) {
            return self::render("Add \"$shortcodeName\" to the allowed shortcodes in the MapSVG settings.", 200, "text");
        } else {
            $shortcode = do_shortcode($shortcode);
            return self::render($shortcode, 200, "html");
        }
    }
}
