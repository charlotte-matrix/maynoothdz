<?php

namespace MapSVG;

/**
 */
return function () {

    $repo = RepositoryFactory::get("map");
    $maps = $repo->find();

    if ($maps["items"]) {
        foreach ($maps["items"] as $map) {
            if ($map->options && isset($map->options["filters"]) && isset($map->options["filters"]["filteredRegionsStatus"])) {
                $map->options["regionsDynamicStatus"] = [
                    "filtered" => $map->options["filters"]["filteredRegionsStatus"],
                ];
                unset($map->options["filters"]["filteredRegionsStatus"]);
            }
            $repo->update($map);
        }
    }
};
