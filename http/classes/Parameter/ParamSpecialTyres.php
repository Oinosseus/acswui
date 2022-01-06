<?php

namespace Parameter;

/**
 * Select multiplie user groups.
 * The available groups are automatically detected
 */
final class ParamSpecialTyres extends ParamEnumMulti {

    // to add tyres, just enhance this array
    const LegalTyres = array(
                             'H'=>"Hard",
                             'I'=>"Intermediate",
                             'M'=>"Medium",
                             'S'=>"Soft",
                             'ST'=>"Street",
                             'V'=>"Vintage",
                            );


    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);

        foreach (ParamSpecialTyres::LegalTyres as $value=>$label)
            new EnumItem($this, $value, $label);
    }

}
