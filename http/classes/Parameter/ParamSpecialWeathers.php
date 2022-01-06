<?php

namespace Parameter;

/**
 * Select multiplie weathers
 * The available weathers are automatically detected
 */
final class ParamSpecialWeathers extends ParamEnumMulti {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
        foreach (\DbEntry\Weather::listWeathers() as $w) {
            new EnumItem($this, $w->id(), $w->name());
        }

        // set to empty by default
        $this->setValue([]);
    }

}
