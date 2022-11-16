<?php

namespace ParameterSpecial;

class UserFormatDate extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        $now = new \DateTime("now");

        $formats = array("Y-m-d H:i:s", "D, d M y H:i:s");
        foreach ($formats as $f)
            new \Parameter\EnumItem($this, $f, $now->format($f));
        $this->setValue($this->EnumItemList[0]->value());
    }


    final protected function cloneXtraAttributes($base) {
    }
}
