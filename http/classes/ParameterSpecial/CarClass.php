<?php

namespace ParameterSpecial;

/**
 * Select a CarClass
 * The available classes are automatically detected
 */
final class CarClass extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);

        foreach (\DbEntry\CarClass::listClasses() as $t) {
            new \Parameter\EnumItem($this, $t->id(), $t->name());
        }

        // set to empty by default
        $this->setValue($this->EnumItemList[0]->value());
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
