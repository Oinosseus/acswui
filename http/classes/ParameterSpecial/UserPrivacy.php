<?php

namespace ParameterSpecial;

class UserPrivacy extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "Public",   _("Public"));
        new \Parameter\EnumItem($this, "ActiveDriver",   _("Active Driver"));
        new \Parameter\EnumItem($this, "Community",   _("Community"));
        new \Parameter\EnumItem($this, "Private",   _("Private"));
        $this->setValue("ActiveDriver");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
