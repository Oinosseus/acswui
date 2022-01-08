<?php

namespace Parameter;

class ParamSpecialUserTimezone extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, \Core\Config::LocalTimeZone, _("Server Time"));
        foreach (\DateTimeZone::listIdentifiers() as $tz)
            new EnumItem($this, $tz, $tz);
        $this->setValue($this->EnumItemList[0]->value());
    }


    final protected function cloneXtraAttributes($base) {
    }
}
