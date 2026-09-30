<?php

namespace MapSVG;

class Json
{
    public static function fix($json)
    {
        if (!is_string($json) || empty($json)) {
            return false;
        }

        // Fix unquoted property names (outside of double quotes)
        $json = preg_replace('/([{,])\s*([a-zA-Z0-9_]+)\s*:(?=[^"]*(?:"[^"]*"[^"]*)*$)/', '$1"$2":', $json);

        // Try to decode
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_SLASHES);
        }

        return false;
    }

    public static function debug()
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return 'No errors';
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters';
            default:
                return 'Unknown error';
        }
    }
}
