<?php

namespace Parameter;

class ParamSpecialUserPrivacy extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, "Public",   _("Public"));
        new EnumItem($this, "ActiveDriver",   _("Active Driver"));
        new EnumItem($this, "Community",   _("Community"));
        new EnumItem($this, "Private",   _("Private"));
        $this->setValue("ActiveDriver");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
