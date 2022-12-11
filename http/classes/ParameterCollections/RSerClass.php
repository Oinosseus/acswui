<?php

declare(strict_types=1);
namespace ParameterCollections;

class RSerClass extends \Parameter\Collection {

    public function __construct(\Parameter\Collection $base = NULL) {
        parent::__construct($base, NULL, $key = "RSerClass",
                            $label = _("Race Series Class"),
                            $description = _("Parameters for race series car calsses"));

        if ($base === NULL) $this->createBaseStructure();

    }


    private function createBaseStructure() {



        // --------------------------------------------------------------------
        //  General Settings
        // --------------------------------------------------------------------

        // $pc = new \Parameter\Collection(NULL, $this,
        //                                 "General",
        //                                 _("General Settings"),
        //                                 _("General class settings"));

        new \Parameter\ParamString(NULL, $this,
                                   "Name",
                                   _("Name"),
                                   _("Name of this class in context of the race series"));


        // --------------------------------------------------------------------
        //  Qualification Settings
        // --------------------------------------------------------------------

        $pc = new \Parameter\Collection(NULL, $this,
                                  "Qualification",
                                  _("Qualification"),
                                  _("Qualification settings the class"));

        new \Parameter\ParamInt(NULL, $pc,
                                "QualMinPts",
                                _("Min Points"),
                                _("Minimum points from last season which driver needs for registration"));


        $p = new \ParameterSpecial\RankingGroup(NULL, $pc,
                                                "QualMinRnkGrp",
                                                _("Min Group"),
                                                _("The minimum driver ranking group a driver needs to have to register for this class"));
        $p->setValue(0);


        // --------------------------------------------------------------------
        //  BOP Settings
        // --------------------------------------------------------------------

        $pc = new \Parameter\Collection(NULL, $this,
                                        "BOP",
                                        _("BOP Settings"),
                                        _("Settings for Ballance Of Performance"));

        $p = new \Parameter\ParamInt(NULL, $pc, "BopBallast", _("Ballast"), _("Additional ballast for this class"), "kg", 0);
        $p->setMin(0);
        $p->setMax(999);

        $p = new \Parameter\ParamInt(NULL, $pc, "BopRestrictor", _("Restrictor"), _("Additional restrictor for this class"), "&percnt;", 0);
        $p->setMin(0);
        $p->setMax(100);

   }
}
