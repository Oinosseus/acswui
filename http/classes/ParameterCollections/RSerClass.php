<?php

declare(strict_types=1);
namespace ParameterCollections;

class RSerClass extends \Parameter\Collection {

    public function __construct(?\Parameter\Collection $base = NULL) {
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

        $p = new \Parameter\ParamInt(NULL, $pc,
                                     "Priority",
                                     _("Priority"),
                                     _("Defines which class is most important (0) and which are less important (higher values)"));
        $p->setMin(0);
        $p->setMax(9);

        new \Parameter\ParamBool(NULL, $pc,
                                 "FillEntries",
                                 _("Fill Entrylist"),
                                 _("If activated, empty pitboxes are filled with random cars of this class"),
                                 "", FALSE);


        // --------------
        //  Registration

        $pc = new \Parameter\Collection(NULL, $pc,
                                  "Registration",
                                  _("Registration"),
                                  _("Settings for registration requirements"));

        new \Parameter\ParamInt(NULL, $pc,
                                "RegMinPts",
                                _("Min Points"),
                                _("Minimum points from last season which driver needs for registration"));


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
