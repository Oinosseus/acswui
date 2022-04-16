<?php

namespace Parameter;

class ParamSpecialUserFormatLength extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "SI", _("SI-System [m, km, Mm]"));
        new EnumItem($this, "OLD", _("SI until km [m, km]"));
        $this->setValue("SI");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
