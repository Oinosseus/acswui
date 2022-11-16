<?php

namespace ParameterSpecial;

class UserTimezone extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        foreach (\DateTimeZone::listIdentifiers() as $tz)
            new \Parameter\EnumItem($this, $tz, $tz);
        $this->setValue("UTC");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
