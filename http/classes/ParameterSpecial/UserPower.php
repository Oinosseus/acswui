<?php

namespace ParameterSpecial;

class UserPower extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "SI", _("SI-System [W]"));
        new \Parameter\EnumItem($this, "OLD", _("Horsepower [PS]"));
        $this->setValue("SI");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
