<?php

namespace ParameterSpecial;

/**
 * Select a driver ranking group
 * The available groups are automatically detected
 */
final class RankingGroup extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);

        for ($i=\Core\Config::DriverRankingGroups-1; $i >= 0; --$i) {
            $name = \Core\Acswui::getParam("DriverRankingGroup{$i}Name");
            new \Parameter\EnumItem($this, $i, $name);
        }

        $this->setValue(\Core\Config::DriverRankingGroups-1);
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
