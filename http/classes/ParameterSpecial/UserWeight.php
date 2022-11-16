<?php

namespace ParameterSpecial;

class UserWeight extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "SI", _("SI-System [kg, Mg]"));
        new \Parameter\EnumItem($this, "OLD", _("Old-Stylish [kg, t]"));
        $this->setValue("SI");
    }


    final protected function cloneXtraAttributes($base) {
    }

}
