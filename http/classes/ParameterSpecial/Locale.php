<?php

namespace ParameterSpecial;

class Locale extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "auto", _("auto detect"));
        foreach (\Core\Config::Locales as $l) {
            new \Parameter\EnumItem($this, $l,   $l);
        }
        $this->setValue($this->EnumItemList[0]->value());
    }


    final protected function cloneXtraAttributes($base) {
    }
}
