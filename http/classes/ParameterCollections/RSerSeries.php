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
                                        _("Session Settings"),
                                        _("Settings for Session runs"));

        new \ParameterSpecial\ServerPreset(NULL, $pc,
                                           "SessionPresetRace",
                                           _("Race"),
                                           _("Server preset for races"));

        new \ParameterSpecial\ServerPreset(NULL, $pc,
                                           "SessionPresetQual",
                                           _("Qualifying"),
                                           _("Server preset for qualification"));


        // --------------------------------------------------------------------
        //  Points
        // --------------------------------------------------------------------

        $pc = new \Parameter\Collection(NULL, $this,
                                        "Points",
                                        _("Race Result Points"),
                                        _("Settings got point assignments for races"));

        $p = new \Parameter\ParamInt(NULL, $pc,
                                     "PtsGainFact",
                                     _("Gain Factor"),
                                     _("Linear factor of significant point increase"),
                                     "pts", 3);
        $p->setMin(0);
        $p->setMax(99);

        $p = new \Parameter\ParamInt(NULL, $pc,
                                     "PtsGainPos",
                                     _("Gain Position"),
                                     _("Position where significant point increase starts"),
                                     "pts", 3);
        $p->setMin(0);
        $p->setMax(99);

        $p = new \Parameter\ParamInt(NULL, $pc,
                                     "PtsPosInc",
                                     _("Linear Position"),
                                     _("Position where incrementation of points starts"),
                                     "pos", 15);
        $p->setMin(0);
        $p->setMax(99);

        $p = new \Parameter\ParamInt(NULL, $pc,
                                     "PtsPosCons",
                                     _("Consolation Position"),
                                     _("Last position wich will earn a single consolation point"),
                                     "pos", 20);
        $p->setMin(0);
        $p->setMax(999);

        $p = new \Parameter\ParamInt(NULL, $pc,
                                     "PtsStrikeRslt",
                                     _("Strike Results"),
                                     _("Amount of weakest events which will not be considered for final results."),
                                     "", 1);
        $p->setMin(0);
        $p->setMax(999);

        $p = new \Parameter\ParamEnum(NULL, $pc,
                                     "PtsClassSum",
                                     _("Class Sum"),
                                     _("This defines how team car points are summarized within a class for overall ranking."));
        new \Parameter\EnumItem($p, "B1", _("Best Result"));
        new \Parameter\EnumItem($p, "B2", _("Best two Results"));
        new \Parameter\EnumItem($p, "B3", _("Best three Results"));
        new \Parameter\EnumItem($p, "B4", _("Best four Results"));
        new \Parameter\EnumItem($p, "AVRG", _("Average"));
        $p->setValue("B2");


        // --------------------------------------------------------------------
        //  General BOP
        // --------------------------------------------------------------------

        $pc = new \Parameter\Collection(NULL, $this,
                                        "BOP",
                                        _("Ballance Of Performance"),
                                        _("General BOP settings"));

        new \Parameter\ParamBool(NULL, $pc,
                                 "BopIncremental",
                                 _("Slow Incremental"),
                                 _("If deactivated, the full BOP is applied after the first event. If activated, the BOP increases step by step per event."),
                                 "", TRUE);


        // set all deriveable and visible
        // $this->setAllAccessible();
   }
}
