<?php

/**
 * @var Map $map
 */
return function (&$map) {


    require_once MAPSVG_PLUGIN_DIR . '/php/Core/JsToJson.php';
    if (!is_string($map["options"])) {
        $map["options"] = wp_json_encode($map["options"], JSON_UNESCAPED_UNICODE);
    }
    $map['options'] = \MapSVG\JsToJson::removeQuotesFromBooleansAndNull($map['options']);
    $map['options'] = json_decode($map['options'], true);

    return $map;
};
