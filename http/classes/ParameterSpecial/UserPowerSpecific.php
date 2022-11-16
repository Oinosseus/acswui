<?php

namespace ParameterSpecial;

class UserPowerSpecific extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "SI1", _("SI-System [W/kg]"));
        new \Parameter\EnumItem($this, "SI2", _("SI-System [kg/W]"));
        new \Parameter\EnumItem($this, "OLD1", _("Old-Stylish [kg/PS]"));
        new \Parameter\EnumItem($this, "OLD2", _("Old-Stylish [PS/t]"));
        $this->setValue("SI1");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
