<?php

namespace Parameter;

class ParamSpecialUserPower extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "SI", _("SI-System [W]"));
        new EnumItem($this, "OLD", _("Horsepower [PS]"));
        $this->setValue("SI");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
