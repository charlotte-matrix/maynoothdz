<?php

namespace MapSVG;

/**
 */
return function () {

    $repo = RepositoryFactory::get("map");
    $maps = $repo->find();

    if ($maps["items"]) {
        foreach ($maps["items"] as $map) {
            if ($map->options && !isset($map->options["useShadowRoot"])) {
                $map->options["useShadowRoot"] = false;
            }
            $repo->update($map);
        }
    }
};
