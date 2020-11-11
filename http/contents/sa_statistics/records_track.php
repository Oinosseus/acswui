<?php

class records_track extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Track Records");
        $this->PageTitle  = "Best Track Lap Times";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

        // class local vars
        $this->CurrentTrackId = Null;
//         $this->CarNameCacheSkin = array();
        $this->CarNameCacheId = array();
    }

    private function getCarNameFromSkin($car_skin_id) {
        global $acswuiDatabase;
        if (array_key_exists($car_skin_id, $this->CarNameCacheSkin)) {
            return $this->CarNameCacheSkin[$car_skin_id];
        } else {
            $query = "SELECT Cars.Name FROM `CarSkins` INNER JOIN Cars ON CarSkins.Car=Cars.Id WHERE CarSkins.Id = $car_skin_id LIMIT 1;";
            $res = $acswuiDatabase->fetch_raw_select($query);
            if (count($res) == 1) {
                $this->CarNameCacheSkin[$car_skin_id] = $res[0]['Name'];
                return $this->CarNameCacheSkin[$car_skin_id];
            }
        }
        return "";
    }



    private function getCarNameFromId($car_id) {
        global $acswuiDatabase;
        if (array_key_exists($car_id, $this->CarNameCacheId)) {
            return $this->CarNameCacheId[$car_id];
        } else {
            $res = $acswuiDatabase->fetch_2d_array("Cars", ['Name'], ['Id'=>$car_id]);
            if (count($res) == 1) {
                $this->CarNameCacheId[$car_id] = $res[0]['Name'];
                return $this->CarNameCacheId[$car_id];
            }
        }
        return "";
    }



    public function getHtml() {
        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;

        // get dictionary of drivers
        $drivers = array();
        foreach ($acswuiDatabase->fetch_2d_array('Users', ['Id', 'Login']) as $row) {
            $id = $row['Id'];
            $drivers[$id] = $row['Login'];
        }

        $html = "";

        // --------------------------------------------------------------------
        //                        Process Post Data
        // --------------------------------------------------------------------

        if (isset($_GET['TRACK_ID'])) {
            $this->CurrentTrackId = (int) $_GET['TRACK_ID'];
        }


        // --------------------------------------------------------------------
        //                            Track Select
        // --------------------------------------------------------------------

        // scan tracks which are used in sessions
        $session_tracks = array(); // 2D-Array
        $rows_tracks = $acswuiDatabase->fetch_2d_array("Tracks", ['Id', 'Name', 'Config'], [], "Name");
        foreach ($rows_tracks as $row_track) {
            $track_id = $row_track['Id'];
            $rows_sessions = $acswuiDatabase->fetch_2d_array("Sessions", ['Id'], ['Track'=>$track_id]);
            if (count($rows_sessions) > 0) {
                $st = array();
                $st['Id'] = $track_id;
                $st['Name'] = $row_track['Name'];
                $st['Config'] = $row_track['Config'];
                $session_tracks[] = $st;
            }
        }

        // preset
        $html .= '<form action="" method="get">';
        $html .= _("Track Select");
        $html .= ' <select name="TRACK_ID" onchange="this.form.submit()">';
        foreach ($session_tracks as $st) {

            // take first session if none is selected yet
            if ($this->CurrentTrackId === Null) {
                $this->CurrentTrackId = $st['Id'];
            }

            $id = $st['Id'];
            $name = $st['Name'];
            if ($st['Config'] != "") $name .= " - " . $st['Config'];
            $selected = ($id == $this->CurrentTrackId) ? "selected" : "";
            $html .= "<option value=\"$id\" $selected >$name</option>";
        }
        $html .= '</select>';
        $html .= '<br>';
        $html .= '</form>';


        // --------------------------------------------------------------------
        //                            Best Laps
        // --------------------------------------------------------------------

        function compare_laptimes($lt1, $lt2) {
            return ($lt1['Laptime'] < $lt2['Laptime']) ? -1 : 1;
        }

        foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id', 'Name'], [], 'Name') as $carclass_row) {
            $carclass_id = $carclass_row['Id'];
            $carclass_name = $carclass_row['Name'];

            // get list of car IDs in carclass
            $cars = array();
            foreach ($acswuiDatabase->fetch_2d_array("CarClassesMap", ['Car'], ['CarClass' => $carclass_id]) as $cars_row) {
                $cars[] = $cars_row['Car'];
            }

            // find drivers best laps
            $driver_best_laps = array();
            foreach ($drivers as $driver_id => $driver_login) {

                $best_lap = stats_driver_best_lap($driver_id, $this->CurrentTrackId, $cars);
                if ($best_lap !== Null) {
                    $best_lap['Driver'] = $driver_login;
                    $driver_best_laps[] = $best_lap;
                }
            }

            // sort
            uasort($driver_best_laps, 'compare_laptimes');

            // generate html
            $html .= "<h1>$carclass_name</h1>";
            $html .= '<table>';
            $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th><th>' . _("Lap Id") . '</th>';
            foreach ($driver_best_laps as $dbl) {
                $html .= "<tr>";
                $html .= "<td>" . HumanValue::format($dbl['Laptime'], "LAPTIME") . "</td>";
                $html .= "<td>" . $dbl['Driver'] . "</td>";
                $html .= "<td>" . $this->getCarNameFromId($dbl['Car']) . "</td>";
                $html .= "<td>" . sprintf("%0.1f", 100 * $dbl['Grip']) . "&percnt;</td>";
                $html .= "<td>" . $dbl['Timestamp'] . "</td>";
                $html .= "<td>" . $dbl['LapId'] . "</td>";
                $html .= "</tr>";
            }
            $html .= '</table>';
        }

        return $html;
    }
}

?>
