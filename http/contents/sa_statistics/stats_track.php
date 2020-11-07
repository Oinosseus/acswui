<?php

class stats_track extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Track Laps");
        $this->PageTitle  = "Track Best Lap Times";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];

        // class local vars
        $this->CurrentTrackId = Null;
        $this->CarSkinCache = array();
    }

    private function getCarName($car_skin_id) {
        global $acswuiDatabase;
        if (array_key_exists($car_skin_id, $this->CarSkinCache)) {
            return $this->CarSkinCache[$car_skin_id];
        } else {
            $query = "SELECT Cars.Name FROM `CarSkins` INNER JOIN Cars ON CarSkins.Car=Cars.Id WHERE CarSkins.Id = $car_skin_id LIMIT 1;";
            $res = $acswuiDatabase->fetch_raw_select($query);
            if (count($res) == 1) {
                $this->CarSkinCache[$car_skin_id] = $res[0]['Name'];
                return $this->CarSkinCache[$car_skin_id];
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
        //                            Bast Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Best Laps") . "</h1>";

        // find drivers best laps
        $driver_best_laps = array();
        foreach ($drivers as $driver_id => $driver_login) {
            // get all sessions of requested track
            $track_id = $this->CurrentTrackId;
            $query = "SELECT Laps.Laptime, Laps.Grip, Laps.Timestamp, Laps.CarSkin FROM `Laps` INNER JOIN Sessions ON Laps.Session=Sessions.Id WHERE Sessions.Track=$track_id AND Laps.User=$driver_id AND Laps.Cuts=0 ORDER BY Laps.Laptime ASC LIMIT 1;";
            $res = $acswuiDatabase->fetch_raw_select($query);
            if (count($res) > 0) {
                $best_lap = array();
                $best_lap['Driver'] = $driver_login;
                $best_lap['Laptime'] = $res[0]['Laptime'];
                $best_lap['Grip'] = $res[0]['Grip'];
                $best_lap['Timestamp'] = $res[0]['Timestamp'];
                $best_lap['Car'] = $this->getCarName($res[0]['CarSkin']);
                $driver_best_laps[] = $best_lap;
            }
        }

        // sort
        function compare_laptimes($lt1, $lt2) {
            return ($lt1['Laptime'] < $lt2['Laptime']) ? -1 : 1;
        }
        uasort($driver_best_laps, 'compare_laptimes');

        $html .= '<table>';
        $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th>';
        foreach ($driver_best_laps as $dbl) {
            $html .= "<tr>";
            $html .= "<td>" . laptime2str($dbl['Laptime']) . "</td>";
            $html .= "<td>" . $dbl['Driver'] . "</td>";
            $html .= "<td>" . $dbl['Car'] . "</td>";
            $html .= "<td>" . sprintf("%0.1f", 100 * $dbl['Grip']) . "&percnt;</td>";
            $html .= "<td>" . $dbl['Timestamp'] . "</td>";
            $html .= "</tr>";
        }
        $html .= '</table>';

        return $html;
    }
}

?>
