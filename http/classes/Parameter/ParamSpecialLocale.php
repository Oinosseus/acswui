<?php

namespace Parameter;

class ParamSpecialLocale extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "auto", _("auto detect"));
        foreach (\Core\Config::Locales as $l) {
            new EnumItem($this, $l,   $l);
        }
        $this->setValue($this->EnumItemList[0]->value());
    }


    final protected function cloneXtraAttributes($base) {
    }
}
