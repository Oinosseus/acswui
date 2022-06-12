<?php

namespace Parameter;

class EnumItem {

    private $Value = NULL;
    private $Label = NULL;


    public function __construct(ParamEnum $param, $value, string $label) {
        $this->Value = $value;
        $this->Label = $label;
        $param->addEnumItem($this);
    }


    public function value() {
        return $this->Value;
    }


    public function label() {
        return $this->Label;
    }

    public function __toString() {
        return "EnumItem(" . $this->Value . ")";
    }
}
