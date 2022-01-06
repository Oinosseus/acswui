<?php

namespace Parameter;

class ParamSpecialPenaltyType extends ParamEnum {

    // to add tyres, just enhance this array
    const BaseSet = array(
                             'dt'=>"Drivetru",
                             'sg1'=>"Stop&amp;Go 1s",
                             'sg2'=>"Stop&amp;Go 2s",
                             'sg3'=>"Stop&amp;Go 3s",
                             'sg5'=>"Stop&amp;Go 5s",
                             'sg10'=>"Stop&amp;Go 10s",
                             'sg15'=>"Stop&amp;Go 15s",
                             'sg20'=>"Stop&amp;Go 20s",
                             'sg30'=>"Stop&amp;Go 30s",
                            );


    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "", bool $allow_disqualify=FALSE) {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new EnumItem($this, 'dt',   _("Drivetru"));
        new EnumItem($this, 'sg1',  _("Stop&amp;Go 1s"));
        new EnumItem($this, 'sg2',  _("Stop&amp;Go 2s"));
        new EnumItem($this, 'sg3',  _("Stop&amp;Go 3s"));
        new EnumItem($this, 'sg5',  _("Stop&amp;Go 5s"));
        new EnumItem($this, 'sg10', _("Stop&amp;Go 10s"));
        new EnumItem($this, 'sg15', _("Stop&amp;Go 15s"));
        new EnumItem($this, 'sg20', _("Stop&amp;Go 20s"));
        new EnumItem($this, 'sg25', _("Stop&amp;Go 25s"));
        new EnumItem($this, 'sg30', _("Stop&amp;Go 30s"));

        if ($allow_disqualify) {
            new EnumItem($this, "dsq", _("Disqualify"));
        }

        $this->setValue("dt");
    }


    final protected function cloneXtraAttributes($base) {
    }
}
