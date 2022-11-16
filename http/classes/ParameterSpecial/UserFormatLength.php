<?php

namespace ParameterSpecial;

class UserFormatLength extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "SI", _("SI-System [m, km, Mm]"));
        new \Parameter\EnumItem($this, "OLD", _("SI until km [m, km]"));
        $this->setValue("SI");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
