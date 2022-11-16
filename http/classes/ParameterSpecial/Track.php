<?php

namespace ParameterSpecial;

/**
 * Select a track
 * The available Tracks are automatically detected
 */
final class Track extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);


        foreach (\DbEntry\Track::listTracks() as $t) {
            new \Parameter\EnumItem($this, $t->id(), $t->name());
        }

        // set to empty by default
        $this->setValue($this->EnumItemList[0]->value());
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }


    //! @return The according Track object
    public function track() {
        return \DbEntry\Track::fromId($this->value());
    }
}
