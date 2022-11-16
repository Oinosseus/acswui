<?php

namespace ParameterSpecial;

/**
 * Select multiplie weathers
 * The available weathers are automatically detected
 */
final class Weathers extends \Parameter\ParamEnumMulti {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
        foreach (\DbEntry\Weather::listWeathers() as $w) {
            new \Parameter\EnumItem($this, $w->id(), $w->name());
        }

        // set to empty by default
        $this->setValue([]);
    }

    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
