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

        $pc = new \Parameter\Collection(NULL, $this,
                                        "General",
                                        _("General Settings"),
                                        _("General class settings"));

        new \Parameter\ParamString(NULL, $pc,
                                   "Name",
                                   _("Name"),
                                   _("Name of this class in context of the race series"));


        // --------------
        //  Qualifcation

        $pc = new \Parameter\Collection(NULL, $pc,
                                  "Qualification",
                                  _("Qualification"),
                                  _("Qualification settings the class"));

        new \Parameter\ParamInt(NULL, $pc,
                                "QualMinPts",
                                _("Min Points"),
                                _("Minimum points from last season which driver needs for registration"));


        $p = new \ParameterSpecial\RankingGroup(NULL, $pc,
                                                "QualMaxRnkGrp",
                                                _("Max Group"),
                                                _("The maximum driver ranking group a driver needs to have to register for this class"));
        $p->setValue(\Core\Config::DriverRankingGroups - 1);


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

        //--------------
        //  General BOP

        $pc2 = new \Parameter\Collection(NULL, $pc,
                                        "BopGeneral",
                                        _("General BOP"),
                                        _("Additional ballast and restrictor for all cars in this class."));

        $p = new \Parameter\ParamInt(NULL, $pc2, "BopBallastOffset", _("Ballast"), _("Additional ballast for all cars in this class"), "kg", 0);
        $p->setMin(0);
        $p->setMax(999);

        $p = new \Parameter\ParamInt(NULL, $pc2, "BopRestrictorOffset", _("Restrictor"), _("Additional restrictor for all cars in this class"), "&percnt;", 0);
        $p->setMin(0);
        $p->setMax(100);


        //----------------------
        //  Incremental Ballast

        $pc2 = new \Parameter\Collection(NULL, $pc,
                                        "BopIncrementalBallast",
                                        _("Incremental Ballast"),
                                        _("Ballast that is applied based on current season position"));

        $p = new \Parameter\ParamInt(NULL, $pc2, "BopBallastGain", _("Increase"), _("The ballast which is added per position"), "kg", 5);
        $p->setMin(0);
        $p->setMax(999);

        $p = new \Parameter\ParamInt(NULL, $pc2, "BopBallastPosition", _("Start"), _("The position in the current season results, where the ballast is started to be applied"), "pos", 5);
        $p->setMin(0);
        $p->setMax(999);


        //----------------------
        //  Incremental Restrictor

        $pc2 = new \Parameter\Collection(NULL, $pc,
                                        "BopIncrementalRestrictor",
                                        _("Incremental Restrictor"),
                                        _("Restrictor that is applied based on current season position"));

        $p = new \Parameter\ParamInt(NULL, $pc2, "BopRestrictorGain", _("Increase"), _("The restrictor which is added per position"), "%", 1);
        $p->setMin(0);
        $p->setMax(999);

        $p = new \Parameter\ParamInt(NULL, $pc2, "BopRestrictorPosition", _("Start"), _("The position in the current season results, where the restrictor is started to be applied"), "pos", 3);
        $p->setMin(0);
        $p->setMax(999);
   }
}
