<?php

class records_car extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Car Records");
        $this->PageTitle  = "Best Car Lap Times";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

        // class local vars
        $this->CurrentCarClassId = Null;
    }


    private function get_driver_best_lap($user_id, $track_id, $cars_id_list) {
        // return the best lap of a user on a certain track with one of the cars in list
        // If no best lap is found, Null is returned.
        // Else an array with keys LapId, Laptime, Grip, Timestamp, Car is returned
        global $acswuiDatabase;

        $laptime = Null;
        $grip = Null;
        $timestamp = Null;
        $car = Null;
        $lapid = Null;

        $query = "SELECT Laps.Id, Laps.Laptime, Laps.Grip, Laps.Timestamp, Laps.CarSkin, CarSkins.Car FROM `Laps`";
        $query .= " INNER JOIN Sessions ON Laps.Session=Sessions.Id";
        $query .= " INNER JOIN CarSkins ON Laps.CarSkin=CarSkins.Id";
        $query .= " WHERE Sessions.Track=$track_id AND Laps.User=$user_id AND Laps.Cuts=0 ORDER BY Laps.Id ASC;";
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {

            // ingore not requested cars
            if (!in_array($row['Car'], $cars_id_list)) continue;

            if ($laptime === Null || $row['Laptime'] < $laptime) {
                $laptime = $row['Laptime'];
                $grip = $row['Grip'];
                $timestamp = $row['Timestamp'];
                $car = $row['Car'];
                $lapid = $row['Id'];
            }
        }

        if ($laptime === Null) {
            return Null;
        } else {
            $ret = array();
            $ret['Laptime'] = $laptime;
            $ret['Grip'] = $grip;
            $ret['Timestamp'] = $timestamp;
            $ret['Car'] = $car;
            $ret['LapId'] = $lapid;
            return $ret;
        }
    }


    public function getHtml() {
        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;

        $html = "";

        // --------------------------------------------------------------------
        //                        Process Request Data
        // --------------------------------------------------------------------

        if (isset($_GET['CARCLASS_ID'])) {
            $this->CurrentCarClassId = (int) $_GET['CARCLASS_ID'];
        }


        // --------------------------------------------------------------------
        //                            Car Class Select
        // --------------------------------------------------------------------

        $html .= '<form action="" method="get">';
        $html .= _("Car Class");
        $html .= ' <select name="CARCLASS_ID" onchange="this.form.submit()">';
        foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id', 'Name']) as $row) {

            // take first session if none is selected yet
            if ($this->CurrentCarClassId === Null) {
                $this->CurrentCarClassId = $row['Id'];
            }

            $id = $row['Id'];
            $name = $row['Name'];
            $selected = ($id == $this->CurrentCarClassId) ? "selected" : "";
            $html .= "<option value=\"$id\" $selected >$name</option>";
        }
        $html .= '</select>';
        $html .= '<br>';
        $html .= '</form>';



        // --------------------------------------------------------------------
        //                            Gather Data
        // --------------------------------------------------------------------

        // get allowed cars of class
        $cars = array();
        $cars_ids = array();
        $query = "SELECT Cars.Id, Cars.Brand, Cars.Name FROM CarClassesMap";
        $query .= " INNER JOIN Cars ON Cars.Id=CarClassesMap.Car";
        $query .= " WHERE CarClassesMap.CarClass = " . $this->CurrentCarClassId;
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $cars[$row['Id']] = ['Brand'=>$row['Brand'], 'Name'=>$row['Name']];
            $cars_ids[] = $row['Id'];
        }

        // scan tracks that have been driven with this car class
        $tracks = array();
        $tracks_ids = array();
        $query = "SELECT Tracks.Id, Tracks.Name, CarSkins.Car FROM Laps";
        $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
        $query .= " INNER JOIN CarSkins ON CarSkins.Id=Laps.CarSkin";
        $query .= " INNER JOIN Tracks ON Tracks.Id=Sessions.Track";
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $id = $row['Id'];
            $name = $row['Name'];

            // ignore wrong car class
            if (!in_array($row['Car'], $cars_ids)) continue;

            // ignore redundant tracks
            if (in_array($id, $tracks_ids)) continue;

            // add track to list
            $tracks[] = ['Id'=>$id, 'Name'=>$name];
            $tracks_ids[] = $id;
        }

        // get dictionary of drivers
        $drivers = array();
        foreach ($acswuiDatabase->fetch_2d_array('Users', ['Id', 'Login']) as $row) {
            $id = $row['Id'];
            $drivers[$id] = $row['Login'];
        }



        // --------------------------------------------------------------------
        //                            Get Best Laps
        // --------------------------------------------------------------------

        function compare_laptimes($lt1, $lt2) {
            return ($lt1['Laptime'] < $lt2['Laptime']) ? -1 : 1;
        }

        foreach ($tracks as $track) {
            $track_id = $track['Id'];
            $track_name = $track['Name'];

            // find drivers best laps
            $driver_best_laps = array();
            foreach ($drivers as $driver_id => $driver_login) {

                $best_lap = stats_driver_best_lap($driver_id, $track_id, $cars_ids);
                if ($best_lap !== Null) {
                    $best_lap['Driver'] = $driver_login;
                    $driver_best_laps[] = $best_lap;
                }
            }

            // sort
            uasort($driver_best_laps, 'compare_laptimes');

            // generate html
            $html .= "<h1>$track_name</h1>";
            $html .= '<table>';
            $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th><th>' . _("Lap Id") . '</th>';
            foreach ($driver_best_laps as $dbl) {
                $car_brand = $cars[$dbl['Car']]['Brand'];
                $car_name = $cars[$dbl['Car']]['Name'];
                $html .= "<tr>";
                $html .= "<td>" . HumanValue::format($dbl['Laptime'], "LAPTIME") . "</td>";
                $html .= "<td>" . $dbl['Driver'] . "</td>";
                $html .= "<td>$car_brand - $car_name</td>";
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
