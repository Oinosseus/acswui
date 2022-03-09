<?php

namespace Core;

//! Defines the default paramet collection for \DbEntry\Sessionchedule objects
class SessionScheduleDefault {

    private static $BaseParameterCollection = NULL;
    private static $ParameterCollection = NULL;

    private function __construct() {
    }


    private static function filepath() {
        return \Core\Config::AbsPathData . "/htcache/SessionScheduleDefault.json";
    }

    //! @return The Collection object, that stores all parameters
    public static function parameterCollection() {

        // defaine parameter base collection
        if (SessionScheduleDefault::$BaseParameterCollection === NULL) {
            SessionScheduleDefault::$BaseParameterCollection = new \Parameter\Collection(NULL, NULL, "SessionSchedule", _("Session Schedule"), _("Setting for a scheduled session"));

            // ----------------------------------------------------------------
            //                          Basics
            // ----------------------------------------------------------------

            $pc1 = new \Parameter\Collection(NULL, SessionScheduleDefault::$BaseParameterCollection, "Basics", _("Basics"), _("Basic settings"));

            $p = new \Parameter\ParamString(NULL, $pc1, "Name", _("Name"), _("An arbitrary name for this schedule item"), "", "New Schedule Item");
            $p = new \Parameter\ParamSpecialCarClass(NULL, $pc1, "CarClass", _("Car Class"), _("The car class to race with"));
            $p = new \Parameter\ParamSpecialTrack(NULL, $pc1, "Track", _("Track"), _("The track to be raced"));

            $pc2 = new \Parameter\Collection(NULL, $pc1, "Event", _("Event"), _("Settings for the main event"));
            $p = new \Parameter\ParamDateTime(NULL, $pc2, "EventStart", _("Date"), _("Date when the session shall start"));
            $p->setValue(\Core\Core::now()->add(new \DateInterval("P7D"))->format("Y-m-d H:i")); //set to next week to prevent accidental start of new items
            $p = new \Parameter\ParamInt(NULL, $pc2, "SessionEntryList", _("EntryList Session"), _("Use the result of this session as entry list, if zero, the order of registration will be used"), "Session-Id", 0);
            $p = new \Parameter\ParamSpecialServerPreset(NULL, $pc2, "ServerPreset", _("Server Preset"), _("Select the server preset for the event"));
            $p = new \Parameter\ParamSpecialServerSlot(NULL, $pc2, "ServerSlot", _("Server Slot"), _("Select on which server slot the session shall be driven"));

            $pc2 = new \Parameter\Collection(NULL, $pc1, "Practice", _("Practice Loop"), _("Setup a session loop for practice"));
            $p = new \Parameter\ParamBool(NULL, $pc2, "PracticeEna", _("Enable"), _("Enable practice session loop"), "", FALSE);
            $p = new \Parameter\ParamSpecialServerPreset(NULL, $pc2, "PracticePreset", _("Server Preset"), _("Select the server preset for the practice"));


            // ----------------------------------------------------------------
            //                           BOP
            // ----------------------------------------------------------------

            if (\Core\Config::DriverRankingGroups > 1) {
                $pc1 = new \Parameter\Collection(NULL, SessionScheduleDefault::$BaseParameterCollection, "BopDrvRnk", _("Ballance Driver Ranking"), _("Ballance of performacne based on driver ranking"));

                for ($i = 1; $i <= \Core\Config::DriverRankingGroups; ++$i) {
                    $grp_label = \COre\ACswui::parameterCollection()->child("DriverRankingGroup$i". "Name")->valueLabel();
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



            // set all deriveable and visible
            SessionScheduleDefault::$BaseParameterCollection->setAllAccessible();
        }

        // derive own parameter collection
        if (SessionScheduleDefault::$ParameterCollection === NULL) {
            SessionScheduleDefault::$ParameterCollection = new \Parameter\Collection(SessionScheduleDefault::$BaseParameterCollection, NULL);

            // load from file
            $filepath = SessionScheduleDefault::filepath();
            if (file_exists($filepath)) {
                $data_array = json_decode(file_get_contents($filepath), TRUE);
                if ($data_array !== NULL) SessionScheduleDefault::$ParameterCollection->dataArrayImport($data_array);
            }

        }

        return SessionScheduleDefault::$ParameterCollection;
    }


    //! Save the current parameter collection for default SessionSchedule
    public static function saveParameterCollection() {

        // prepare data
        $data_array = SessionScheduleDefault::$ParameterCollection->dataArrayExport();
        $data_json = json_encode($data_array, JSON_PRETTY_PRINT);

        // write to file
        $filepath = SessionScheduleDefault::filepath();
        $f = fopen($filepath, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$filepath'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
    }

}
