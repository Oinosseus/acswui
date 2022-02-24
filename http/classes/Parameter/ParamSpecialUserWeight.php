<?php

namespace Parameter;

class ParamSpecialUserWeight extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "SI", _("SI-System [kg, Mg]"));
        new EnumItem($this, "OLD", _("Old-Stylish [kg, t]"));
        $this->setValue("SI");
    }


    final protected function cloneXtraAttributes($base) {
    }

}
