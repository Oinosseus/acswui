<?php

namespace Parameter;

/**
 * Select a track
 * The available Tracks are automatically detected
 */
final class ParamSpecialTrack extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);


        foreach (\DbEntry\Track::listTracks() as $t) {
            new EnumItem($this, $t->id(), $t->name());
        }

        // set to empty by default
        $this->setValue($this->EnumItemList[0]->value());
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }
}
