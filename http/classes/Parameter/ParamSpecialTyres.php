<?php

namespace Parameter;

/**
 * Select multiplie user groups.
 * The available groups are automatically detected
 */
final class ParamSpecialTyres extends ParamEnumMulti {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);

        // to add tyres, just enhance this array
        new EnumItem($this, 'H',  "(H) " .  _("Hard"));
        new EnumItem($this, 'I',  "(I) " .  _("Intermediate"));
        new EnumItem($this, 'M',  "(M) " .  _("Medium"));
        new EnumItem($this, 'S',  "(S) " .  _("Soft"));
        new EnumItem($this, 'ST', "(ST) " . _("Street"));
        new EnumItem($this, 'V',  "(V) " .  _("Vintage"));
    }


    final protected function cloneXtraAttributes($base) {
    }
}
