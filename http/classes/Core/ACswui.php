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


    public static function parameterCollection() {
        if (ACswui::$ParameterCollection === NULL) {
            $root_collection = new \Parameter\Collection(NULL, NULL, "ACswui", _("ACswui Settings"), _("Settings to configure the ACswui system"));




            // ----------------------------------------------------------------
            //                          System
            // ----------------------------------------------------------------

            $pc = new \Parameter\Collection(NULL, $root_collection, "System", _("System"), _("General system settings"));

            /////////
            // Core
            $pc2 = new \Parameter\Collection(NULL, $pc, "Core", _("Core"), _("Core Settings"));

            // Cronjobs
            $pc3 = new \Parameter\Collection(NULL, $pc2, "Cronjobs", _("Cronjobs"), _("Settings for Cronjobs"));
            $p = new \Parameter\ParamTime(NULL, $pc3, "CronjobDailyExecutionTime", _("Cronjob Execution Time"), _("Time when daily cronjobs shall be executed (typically during night time)\nBe aware that this is server-time-zone"), "Server-Time", "03:30");
            $p = new \Parameter\ParamBool(NULL, $pc3, "SessionAutomatic", _("Activate Session Automatic"), _("The Session Automatic can be disabled to allow updates"), "", TRUE);

            // Community
            $pc3 = new \Parameter\Collection(NULL, $pc2, "Community", _("Community"), _("Community Settings"));
            $p = new \Parameter\ParamInt(NULL, $pc3, "NonActiveDrivingDays", _("Non-Active Driving Days"), _("When the last lap of a user is longer days ago than this value, he or she is considered to be an inactive driver"), "", 30);
            $p->setMin(1);
            $p->setMax(3650);
            $p = new \Parameter\ParamInt(NULL, $pc3, "CommunityLastLoginDays", _("Non-Active Community Login Days"), _("When a user has not logged in into the system for this amount of days he or she is not considered to be a community member\nAdditionally an user must be an active driver to be considered as community member"), "", 90);
            $p->setMin(1);
            $p->setMax(3650);


            ////////////
            // Discord
            $pc2 = new \Parameter\Collection(NULL, $pc, "Discord", _("Discord"), _("Discord Integration"));

            // Manual Start
            $pc3 = new \Parameter\Collection(NULL, $pc2, "DiscordManual", _("Manual Start"), _("Announce when manually starting a session"));
            $p = new \Parameter\ParamString(NULL, $pc3, "DiscordManualWebhookUrl", _("Webhook URL"), _("The Webhook URL to write a message to. Leave Empty to not send messages."), "", "");
            $p = new \Parameter\ParamString(NULL, $pc3, "DiscordManualWebhookMention", _("Mention Role-Id"), _("The ID of the Role that shall be mentioned (leave empty to not mention roles"), "", "");

            // Schedule
            $pc3 = new \Parameter\Collection(NULL, $pc2, "DiscordSchedule", _("Schedule Start"), _("Announce start of a scheduled session"));
            $p = new \Parameter\ParamString(NULL, $pc3, "DiscordScheduleWebhookUrl", _("Webhook URL"), _("The Webhook URL to write a message to. Leave Empty to not send messages."), "", "");
            $p = new \Parameter\ParamString(NULL, $pc3, "DiscordScheduleWebhookMention", _("Mention Role-Id"), _("The ID of the Role that shall be mentioned (leave empty to not mention roles"), "", "");


            // ----------------------------------------------------------------
            //                        Driver Ranking
            // ----------------------------------------------------------------

            $pc = new \Parameter\Collection(NULL, $root_collection, "DriverRanking", _("Driver Ranking"), _("Settings for driver ranking"));
            $p = new \Parameter\ParamInt(NULL, $pc, "DriverRankingDays", _("Days"), _("Amount of days in the past over which the driver ranking shall be calculated"), "d", 30);
            $p->setMin(0);
            $p->setMax(999);

            ///////////////////
            //  Ranking Points
            $pc2 = new \Parameter\Collection(NULL, $pc, "DriverRankingPoints", _("Ranking Points"), _("Adjusting ranking points"));

            // experience
            $coll = new \Parameter\Collection(NULL, $pc2, "DriverRankingXp", _("Experience"), _("Drivers earn experience when completing laps"));
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingXpP", _("Practice"), _("Earned experience points by driven distance in practice"), "Points/Mm", 1);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingXpQ", _("Qualifying"), _("Earned experience points by driven distance in qualifying"), "Points/Mm", 10);
            $p->setMin(0);
            $p->setMax(9999);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingXpR", _("Race"), _("Earned experience points by driven distance in races"), "Points/Mm", 20);
            $p->setMin(0);
            $p->setMax(9999);

            // success
            $coll = new \Parameter\Collection(NULL, $pc2, "DriverRankingSx", _("Success"), _("Settings how drivers earn points for their success"));
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
            $coll = new \Parameter\Collection(NULL, $pc2, "DriverRankingSf", _("Safety"), _("Settings for penalty points that drivers get"));
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSfCt", _("Cuts"), _("Points for amount of cuts that were made"), "Points/Cut", -0.1);
            $p->setMin(-9999);
            $p->setMax(0);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSfCe", _("Collision Environment"), _("Points for colliding with the environment"), "Points/Normspeed-Collision", -1.0);
            $p->setMin(-9999);
            $p->setMax(0);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingSfCc", _("Collision Cars"), _("Points for colliding with other cars"), "Points/Normspeed-Collision", -2.0);
            $p->setMin(-9999);
            $p->setMax(0);
            $p = new \Parameter\ParamFloat(NULL, $coll, "DriverRankingCollNormSpeed", _("Nominal Collision-Speed"), _("Nominal speed at which collision a collsion points counts nominal"), "km/h", 100.0);
            $p->setMin(0);
            $p->setMax(999);
            $p = new \Parameter\ParamBool(NULL, $coll, "DriverRankingSfAP", _("Amnesty in Practice"), _("If enabled, safety penalties are not given for practice sessions (only qualifying/race)"), "", TRUE);


            ///////////////////
            //  Ranking Groups
            if (Config::DriverRankingGroups > 1) {
                $pc2 = new \Parameter\Collection(NULL, $pc, "DriverRankingGroups", _("Ranking Groups"), _("Adjusting groups"));

                $p = new \Parameter\ParamEnumMonthly(NULL, $pc2, "DriverRankingGroupCycle", _("Cycle Time"), _("Define how often the ranking groups shall be assigned"));
                $p->setValue("1Mon;2Mon;3Mon;4Mon;5Mon");

                $p = new \Parameter\ParamEnum(NULL, $pc2, "DriverRankingGroupType", _("Threshold Type"), _("points - A driver needs an amount of points to reach a group\ndrivers - a fix amount of top drivers per group\npercent - percentage of points from the absolute top driver"), "");
                new \Parameter\EnumItem($p, "points", _("points"));
                new \Parameter\EnumItem($p, "drivers", _("drivers"));
                new \Parameter\EnumItem($p, "percent", _("percent"));
                $p->setValue("points");

                $i = 1;
                for (; $i < Config::DriverRankingGroups; ++$i) {
                    $coll = new \Parameter\Collection(NULL, $pc2, "DriverRankingGroup$i", _("Ranking Group") . " $i", _("Settings for a driver ranking group"));
                    $p = new \Parameter\ParamString(NULL, $coll, "DriverRankingGroup$i". "Name", _("Name"), _("An arbitrary name for this group"), "", "Group $i");
                    $p = new \Parameter\ParamInt(NULL, $coll, "DriverRankingGroup$i" . "Thld", _("Threshold"), _("The ranking threshold a driver needs to pass to enter this group"), "", 120 - 20 * $i);
                }
                $coll = new \Parameter\Collection(NULL, $pc2, "DriverRankingGroup$i", _("Group") . " $i", _("Settings for a driver ranking group"));
                $p = new \Parameter\ParamString(NULL, $coll, "DriverRankingGroup$i". "Name", _("Name"), _("An arbitrary name for this group"), "", "Group $i");
            }


            // ----------------------------------------------------------------
            //                    Default Session Schedule
            // ----------------------------------------------------------------


            $pc = new \Parameter\Collection(NULL, $root_collection, "SessionSchedule", _("Default Session Schedule"), _("Default settings for session schedules"));

            ///////////
            // Basics

            $pc1 = new \Parameter\Collection(NULL, $pc, "Basics", _("Basics"), _("Basic settings"));

            $p = new \Parameter\ParamString(NULL, $pc1, "Name", _("Name"), _("An arbitrary name for this schedule item"), "", "New Schedule Item");
            $p = new \Parameter\ParamSpecialCarClass(NULL, $pc1, "CarClass", _("Car Class"), _("The car class to race with"));
            $p = new \Parameter\ParamSpecialTrack(NULL, $pc1, "Track", _("Track"), _("The track to be raced"));

            $pc2 = new \Parameter\Collection(NULL, $pc1, "Event", _("Event"), _("Settings for the main event"));
            $p = new \Parameter\ParamDateTime(NULL, $pc2, "EventStart", _("Date"), _("Date when the session shall start"));
            $p->setValue((new \DateTime("now"))->add(new \DateInterval("P7D"))->format("Y-m-d H:i")); //set to next week to prevent accidental start of new items
            $p = new \Parameter\ParamInt(NULL, $pc2, "SessionEntryList", _("EntryList Session"), _("Use the result of this session as entry list, if zero, the order of registration will be used"), "Session-Id", 0);
            $p = new \Parameter\ParamSpecialServerPreset(NULL, $pc2, "ServerPreset", _("Server Preset"), _("Select the server preset for the event"));
            $p = new \Parameter\ParamSpecialServerSlot(NULL, $pc2, "ServerSlot", _("Server Slot"), _("Select on which server slot the session shall be driven"));

            $pc2 = new \Parameter\Collection(NULL, $pc1, "Practice", _("Practice Loop"), _("Setup a session loop for practice"));
            $p = new \Parameter\ParamBool(NULL, $pc2, "PracticeEna", _("Enable"), _("Enable practice session loop"), "", FALSE);
            $p = new \Parameter\ParamSpecialServerPreset(NULL, $pc2, "PracticePreset", _("Server Preset"), _("Select the server preset for the practice"));


            ///////
            // BOP

            if (\Core\Config::DriverRankingGroups > 1) {
                $pc1 = new \Parameter\Collection(NULL, $pc, "BopDrvRnk", _("Ballance Driver Ranking"), _("Ballance of performacne based on driver ranking"));

                for ($i = 1; $i <= \Core\Config::DriverRankingGroups; ++$i) {
                    $grp_label = _("Ranking Group") . " $i";//\Core\ACswui::parameterCollection()->child("DriverRankingGroup$i". "Name")->valueLabel();
                    $pc2 = new \Parameter\Collection(NULL, $pc1, "BopDrvRnkGrp$i", $grp_label, _("Add ballast or restrictors to driver in this ranking group"));

                    $p = new \Parameter\ParamInt(NULL, $pc2, "BopDrvRnkGrpBallast$i", _("Ballast"), _("Additional ballast for this group"), "kg", 0);
                    $p->setMin(0);
                    $p->setMax(999);

                    $p = new \Parameter\ParamInt(NULL, $pc2, "BopDrvRnkGrpRestrictor$i", _("Restrictor"), _("Additional restrictor for this group"), "&percnt;", 0);
                    $p->setMin(0);
                    $p->setMax(100);
                }

                $pc2 = new \Parameter\Collection(NULL, $pc1, "BopNonRnk", "Not Ranked", _("Add ballast or restrictors to drivers which are not in the driver ranking"));
                $p = new \Parameter\ParamInt(NULL, $pc2, "BopNonRnkBallast", _("Ballast"), _("Additional ballast for drivers who are not in the driver ranking"), "kg", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $pc2, "BopNonRnkRestrictor", _("Restrictor"), _("Additional restrictor for drivers who are not in the driver ranking"), "&percnt;", 0);
                $p->setMin(0);
                $p->setMax(100);
            }




            // ----------------------------------------------------------------
            //                        Default User Settings
            // ----------------------------------------------------------------

            $pc = new \Parameter\Collection(NULL, $root_collection, "User", _("Default User Settings"), _("Settings for users"));
            $p = new \Parameter\ParamSpecialUserPrivacy(NULL, $pc, "UserPrivacy", _("Privacy"), _("Privacy settings\n'Private' means nobody can identify me\n'Community' means that all active drivers, which actively use the ACswui system, can identify me - as long as I use the ACswui system as well\n'Active Drivers' means that all other active drivers can identify me - as long as I am an active driver as well\n'Public' means everyone can identify me"));
            $p = new \Parameter\ParamColor(NULL, $pc, "UserColor", _("Color"), _("Your preferred color to better identify you in diagrams"));
            $p = new \Parameter\ParamInt(NULL, $pc, "UserLoginTokenExpire", _("Login Expire"), _("The user login is saved via a token inside a client cookie. This defines the amount of days when this token expires"), "d", 30);
            $p->setMin(1);
            $p->setMax(365);

            $coll = new \Parameter\Collection(NULL, $pc, "UserContentView", _("Content View"), _("View options for special content"));
            $p = new \Parameter\ParamBool(NULL, $coll, "UserRecordsSkipPrivate",_("Skip Private Records"), _("Skip users which do not allow to show private information in car/track records tables"), "", TRUE);
            $p = new \Parameter\ParamEnum(NULL, $coll, "UserTracksOrder", _("Track Order"), _("In which order shall tracks be shown"));
            new \Parameter\EnumItem($p, 'country',  _("By Country"));
            new \Parameter\EnumItem($p, 'alphabet',  _("Alphabetically"));
            $p->setValue("country");

            $coll = new \Parameter\Collection(NULL, $pc, "UserI18nL10n", _("Loc-/Internationalization"), _("Options for localization and internationalization"));
            $p = new \Parameter\ParamSpecialUserCountry(NULL, $coll, "UserCountry", _("Country"), _("Select which country you want to represent"));
            $p = new \Parameter\ParamSpecialLocale(NULL, $coll, "UserLocale", _("Locale"), _("Localization settings"));
            $p = new \Parameter\ParamSpecialUserTimezone(NULL, $coll, "UserTimezone", _("Timezone"), _("Define your preferred timezone"));

            $coll = new \Parameter\Collection(NULL, $pc, "UserUnits", _("Units"), _("Setup preferred units for values"));
            $p = new \Parameter\ParamSpecialUserPower(NULL, $coll, "UserUnitPower", _("Power"), "");
            $p = new \Parameter\ParamSpecialUserPowerSpecific(NULL, $coll, "UserUnitPowerSpecific", _("Specific Power"), "");
            $p = new \Parameter\ParamSpecialUserWeight(NULL, $coll, "UserUnitWeight", _("Weight"), "");
            $p = new \Parameter\ParamSpecialUserFormatDate(NULL, $coll, "UserFormatDate", _("Date/Time Format"), _("How shal date-times be presented"));
            $p = new \Parameter\ParamSpecialUserFormatLength(NULL, $coll, "UserUnitLength", _("Length"), "");

            $coll = new \Parameter\Collection(NULL, $pc, "UserLaptimeDistriDia", _("Laptime Distribution Diagrams"), _("Options to adjust the laptime distribution diagrams"));
            $p = new \Parameter\ParamInt(NULL, $coll, "UserLaptimeDistriDiaMaxDelta", _("Max Delta"), _("Defines the maximum of the x-axis (how much seconds to show)"), "s", 10);
            $p->setMin(1);
            $p->setMax(300);
            $p = new \Parameter\ParamEnumMulti(NULL, $coll, "UserLaptimeDistriDiaAxis", _("Time Axis"), _("The time axis (distance to best session laptime) can be drawn logarithmic or linear"));
            new \Parameter\EnumItem($p, 'linear',  _("Linear"));
            new \Parameter\EnumItem($p, 'logarithmic',  _("Logarithmic"));
            $p->setValue("linear");
            $p = new \Parameter\ParamEnumMulti(NULL, $coll, "UserLaptimeDistriDiaType", _("Available Types"), _("Select which options shall be available for laptime distribution diagrams (just prevents to click wrong buttons)"));
            new \Parameter\EnumItem($p, 'hist',  _("Histogram"));
            new \Parameter\EnumItem($p, 'gauss',  _("Gaussian"));
            $p->setValue("gauss");


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
                        ACswui::$ParameterCollection->dataArrayImport($data_array);
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
