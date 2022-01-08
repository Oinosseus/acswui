<?php

namespace Parameter;

class ParamSpecialUserFormatDate extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        $now = new \DateTime("now", new \DateTimezone(\Core\Config::LocalTimeZone));

        $formats = array("Y-m-d H:i:s", "D, d M y H:i:s");
        foreach ($formats as $f)
            new EnumItem($this, $f, $now->format($f));
        $this->setValue($this->EnumItemList[0]->value());
    }


    final protected function cloneXtraAttributes($base) {
    }
}