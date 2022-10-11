<?php

namespace Parameter;

class ParamSpecialUserPowerSpecific extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "SI1", _("SI-System [W/kg]"));
        new EnumItem($this, "SI2", _("SI-System [kg/W]"));
        new EnumItem($this, "OLD1", _("Old-Stylish [kg/PS]"));
        new EnumItem($this, "OLD2", _("Old-Stylish [PS/t]"));
        $this->setValue("SI1");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
