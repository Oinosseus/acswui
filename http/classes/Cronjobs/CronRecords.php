<?php

namespace Cronjobs;

class CronRecords extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalLap);
    }

    protected function process() {

        // buckets for track and carclass records
        $track_records = array();
        $carclass_records = array();

        // query database for each lap, ordered by track, carclass and laptime for easy iteration
        $query = "SELECT Laps.Id AS LapId, Sessions.Track AS TrackId, CarClassesMap.CarClass AS CarClassId, Laps.User AS UserId " .
                 "FROM `Laps` " .
                 "INNER JOIN Sessions on Laps.Session = Sessions.Id ".
                 "INNER JOIN CarSkins ON Laps.CarSkin = CarSkins.Id ".
                 "INNER JOIN Cars ON CarSkins.Car = Cars.Id ".
                 "INNER JOIN CarClassesMap ON CarClassesMap.Car = Cars.Id ".
                 "WHERE Laps.Cuts=0 ".
                 "ORDER BY Sessions.Track, CarClassesMap.CarClass, Laps.Laptime ASC, Laps.User;";
        $analyzed = 0;  // just for user information
        $users_found_in_track_and_carclass = array();
        $last_track_id = NULL;
        $last_carclass_id = NULL;
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $analyzed += 1;
            $lap_id = $row['LapId'];
            $track_id = $row['TrackId'];
            $carclass_id = $row['CarClassId'];
            $user_id = $row['UserId'];

            // ensure buckets exists
            if (!array_key_exists($track_id, $track_records)) $track_records[$track_id] = array();
            if (!array_key_exists($carclass_id, $track_records[$track_id])) $track_records[$track_id][$carclass_id] = array();
            if (!array_key_exists($carclass_id, $carclass_records)) $carclass_records[$carclass_id] = array();
            if (!array_key_exists($track_id, $carclass_records[$carclass_id])) $carclass_records[$carclass_id][$track_id] = array();

            // reset found users on new track or carclass
            if ($last_track_id !== $track_id || $last_carclass_id !== $carclass_id)
                $users_found_in_track_and_carclass = array();

            // add lap to records table
            if (!in_array($user_id, $users_found_in_track_and_carclass)) {
                $users_found_in_track_and_carclass[] = $user_id;
                $track_records[$track_id][$carclass_id][] = $lap_id;
                $carclass_records[$carclass_id][$track_id][] = $lap_id;
            }

            // remember last track and carclass
            $last_track_id = $track_id;
            $last_carclass_id = $carclass_id;
        }
        $this->verboseOutput("analyzed: $analyzed<br>");

        // store track records
        $f = fopen(\Core\Config::AbsPathData . "/htcache/records_track.json", "w");
        fwrite($f, json_encode($track_records, JSON_PRETTY_PRINT));
        fclose($f);

        // store carclass records
        $f = fopen(\Core\Config::AbsPathData . "/htcache/records_carclass.json", "w");
        fwrite($f, json_encode($carclass_records, JSON_PRETTY_PRINT));
        fclose($f);
    }
}
