<?php

declare(strict_types=1);
namespace ParameterCollections;

class RSerSeries extends \Parameter\Collection {

    public function __construct(\Parameter\Collection $base = NULL) {
        parent::__construct($base, NULL, $key = "RSerSeries",
                            $label = _("Race Series"),
                            $description = _("Parameters for Race Resies"));

        if ($base === NULL) $this->createBaseStructure();

    }


    private function createBaseStructure() {

        // --------------------------------------------------------------------
        //  Session Settings
        // --------------------------------------------------------------------

        $pc = new \Parameter\Collection(NULL, $this,
                                        "SessionSettings",
                                        _("Session Settings").
                                        _("Settings for Session runs"));

        new \ParameterSpecial\ServerPreset(NULL, $pc,
                                           "SessionPresetRace",
                                           _("Race"),
                                           _("Server preset for races"));

        new \ParameterSpecial\ServerPreset(NULL, $pc,
                                           "SessionPresetQual",
                                           _("Qualifying"),
                                           _("Server preset for qualification"));

        // set all deriveable and visible
        // $this->setAllAccessible();
   }
}
