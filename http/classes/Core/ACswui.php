<?php

namespace Core;

/**
 * This class contains unique data.
 * To use it, create an object with 'new' and work with it.
 * All Instances will access the same data.
 */
class ACswui  {

    private static $ParameterCollection = NULL;

    public function __construct() {
    }


    /**
     * Access ACswui parameters statically
     *
     * \Core\ACswui::get("fooBar")
     * is the same as
     * (new \Core\ACswui())->parameterCollection()->child"fooBar")->value()
     * @return The curent value of a certain parameter
     */
    public static function getParam(string $parameter_key) {
        return (new ACswui())->parameterCollection()->child($parameter_key)->value();
    }


    public function parameterCollection() {
        if (ACswui::$ParameterCollection === NULL) {
            $root_collection = new \Parameter\Collection(NULL, NULL, "ACswui", _("ACswui Settings"), _("Settings to configure the ACswui system"));


            // ----------------------------------------------------------------
            //! @todo                   To Be Grouped
            // ----------------------------------------------------------------
            $p = new \Parameter\ParamInt(NULL, $root_collection, "NonActiveDrivingDays", _("Non-Active Driving Days"), _("When the last lap of a user is longer days ago than this value, he or she is considered to be an inactive driver"), "", 30);
            $p->setMin(1);
            $p->setMax(3650);
            $p = new \Parameter\ParamInt(NULL, $root_collection, "CommunityLastLoginDays", _("Non-Active Community Login Days"), _("When a user has not logged in into the system for this amount of days he or she is not considered to be a community member\nAdditionally an user must be an active driver to be considered as community member"), "", 90);
            $p->setMin(1);
            $p->setMax(3650);


            // ----------------------------------------------------------------
            //                        Driver Ranking
            // ----------------------------------------------------------------

            $pc = new \Parameter\Collection(NULL, $root_collection, "DriverRanking", _("Driver Ranking"), _("Settings for driver ranking"));
            $p = new \Parameter\ParamFloat(NULL, $pc, "DriverRankingDays", _("Days"), _("Amount of days in the past over which the driver ranking shall be calculated"), "d", 30);
            $p->setMin(0);
            $p->setMax(999);

            // experience
            $coll = new \Parameter\Collection(NULL, $pc, "DriverRankingXp", _("Experience"), _("Drivers earn experience when completing laps"));
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingXpP", _("Practice"), _("Earned experience points by driven distance in practice"), "Points/Mm", 3);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingXpQ", _("Qualifying"), _("Earned experience points by driven distance in qualifying"), "Points/Mm", 10);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingXpR", _("Race"), _("Earned experience points by driven distance in races"), "Points/Mm", 20);
            $p->setMin(0);
            $p->setMax(9999);

            // success
            $coll = new \Parameter\Collection(NULL, $pc, "DriverRankingSx", _("Success"), _("Settings how drivers earn points for their success"));
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSxBt", _("Best Time"), _("Points for best times per track and car class (per leading position prior to another driver)"), "Points/Position", 0.1);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSxRt", _("Race Time"), _("Points for fastest lap in a race"), "Points/Race", 2.0);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSxQ", _("Qualifying"), _("Points for positions in qualifying sessions (per leading position prior to another driver)"), "Points/Position", 0.5);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSxR", _("Race"), _("Points for positions in race sessions (per leading position prior to another driver)"), "Points/Position", 1.0);
            $p->setMin(0);
            $p->setMax(9999);

            // safety
            $coll = new \Parameter\Collection(NULL, $pc, "DriverRankingSf", _("Safety"), _("Settings for penalty points that drivers get"));
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSfCt", _("Cuts"), _("Points for amount of cuts that were made per driven distance"), "Points/Cut/Mm", -0.1);
            $p->setMin(-9999);
            $p->setMax(0);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSfCe", _("Collision Environment"), _("Points for colliding with the environment per driven distance"), "Points/Collision/Mm", -2.0);
            $p->setMin(-9999);
            $p->setMax(0);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSfCc", _("Collision Cars"), _("Points for colliding with other cars per driven distance"), "Points/Collision/Nomspeed/Mm", -5.0);
            $p->setMin(-9999);
            $p->setMax(0);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingCollNormSpeed", _("Nominal Collision-Speed"), _("Nominal speed at which collision a collsion points counts nominal"), "km/h", 100.0);
            $p->setMin(0);
            $p->setMax(999);
            $p = new \Parameter\ParamBool(NULL, $coll, "DriverRankingSfAP", _("Amnesty in Practice"), _("If enabled, safety penalties are not given for pure practice sessions (sessions without succeeding qualifying/race)"), "", TRUE);


            // ----------------------------------------------------------------
            //                        User Settings
            // ----------------------------------------------------------------

            $pc = new \Parameter\Collection(NULL, $root_collection, "User", _("User Settings"), _("Settings for users"));
            $p = new \Parameter\ParamSpecialLocale(NULL, $pc, "UserLocale", _("Locale"), _("Localization settings"));
            $p = new \Parameter\ParamSpecialUserPrivacy(NULL, $pc, "UserPrivacy", _("Privacy"), _("Privacy settings\n'Private' means nobody can identify me\n'Community' means that all active drivers, which actively use the ACswui system, can identify me - as long as I use the ACswui system as well\n'Active Drivers' means that all other active drivers can identify me - as long as I am an active driver as well\n'Public' means everyone can identify me"));
            $p = new \Parameter\ParamSpecialUserCountry(NULL, $pc, "UserCountry", _("Country"), _("Select which country you want to represent"));
            $p = new \Parameter\ParamSpecialUserFormatDate(NULL, $pc, "UserFormatDate", _("Date/Time Format"), _("How shal date-times be presented"));
            $p = new \Parameter\ParamSpecialUserTimezone(NULL, $pc, "UserTimezone", _("Timezone"), _("Define your preferred timezone"));



            // derive root collection
            $root_collection->setAllAccessible();
            ACswui::$ParameterCollection = new \Parameter\Collection($root_collection, NULL);

            // load data from disk
            $file_path = \Core\Config::AbsPathData . "/acswui_config/acswui.json";
            if (file_exists($file_path)) {
                $ret = file_get_contents($file_path);
                if ($ret === FALSE) {
                    \Core\Log::error("Cannot read from file '$file_path'!");
                } else {
                    $data_array = json_decode($ret, TRUE);
                    if ($data_array == NULL) {
                        \Core\Log::warning("Decoding NULL from json file '$file_path'.");
                    } else {
                        $this->parameterCollection()->dataArrayImport($data_array);
                    }
                }
            } else {
                \Core\Log::debug("Config file '$file_path' does not exist.");
            }
        }

        return ACswui::$ParameterCollection;
    }


    //! Store settings
    public function save() {

        // prepare data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);

        // write to file
        $file_path = \Core\Config::AbsPathData . "/acswui_config/acswui.json";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
    }


}
