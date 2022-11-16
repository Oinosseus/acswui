<?php

namespace ParameterSpecial;

/**
 * Select a server slot
 * The available ServerSlots are automatically detected
 */
final class ServerSlot extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);


        foreach (\Core\ServerSlot::listSlots() as $ss) {
            new \Parameter\EnumItem($this, $ss->id(), $ss->name());
        }

        // set to empty by default
        $this->setValue($this->EnumItemList[0]->value());
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
