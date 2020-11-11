<?php

class stats_session extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Session");
        $this->PageTitle  = "Session Statistics";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

        // class local vars
        $this->SessionId = Null;
    }

    private function getCarName($carskin_id) {
        global $acswuiDatabase;

        $carname = "";
        $carskins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Car'], ['Id'=>$carskin_id]);
        if (count($carskins) == 1) {
            $cars = $acswuiDatabase->fetch_2d_array("Cars", ['Name'], ['Id'=>$carskins[0]['Car']]);
            if (count($cars) == 1) {
                $carname = $cars[0]['Name'];
            }
        }

        return $carname;
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


        // --------------------------------------------------------------------
        //                        Process Post Data
        // --------------------------------------------------------------------

        if (isset($_GET['SESSION_ID'])) {
            $this->SessionId = (int) $_GET['SESSION_ID'];
        }



        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                            Session Select
        // --------------------------------------------------------------------

            // preset
        $html .= '<form action="" method="get">';
        $html .= _("Session Select");
        $html .= ' <select name="SESSION_ID" onchange="this.form.submit()">';
        foreach ($acswuiDatabase->fetch_2d_array("Sessions", ['Id', 'Timestamp', 'Name', 'Track'], [], "Id", FALSE) as $row) {

            // take first session if none is selected yet
            if ($this->SessionId === Null) {
                $this->SessionId = $row['Id'];
            }

            // get track
            $track = "";
            $tracks = $acswuiDatabase->fetch_2d_array("Tracks", ['Name'], ['Id'=>$row['Track']]);
            if (count($tracks) == 1) {
                $track = $tracks[0]['Name'];
            }

            $selected = ($this->SessionId == $row['Id']) ? "selected" : "";
            $id = $row['Id'];
            $name = $row['Timestamp'] . " - $track - " . $row['Name'];
            $html .= "<option value=\"$id\" $selected >$name</option>";
        }
        $html .= '</select>';
        $html .= '<br>';
        $html .= '</form>';





        // --------------------------------------------------------------------
        //                               Leaderboard
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Leaderboard") . "</h1>";


        $html .= '<table>';
        $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Grip") . '</th>';

        $listed_users = [];
        $laps = $acswuiDatabase->fetch_2d_array("Laps", ['User', 'Laptime', 'Grip', 'CarSkin'], ['Session'=>$this->SessionId, 'Cuts'=>0], "Laptime", TRUE);
        foreach ($laps as $row) {
            if (in_array($row['User'], $listed_users)) continue;

            $html .= '<tr>';
            $html .= "<td>" . HumanValue::format($row['Laptime'], "LAPTIME") . "</td>";
            $html .= "<td>" . $drivers[$row['User']] . "</td>";
            $html .= "<td>" . $this->getCarName($row['CarSkin']) . "</td>";
            $html .= "<td>" . sprintf("%0.1f", 100 * $row['Grip']) . "&percnt;</td>";
            $html .= '</tr>';

            $listed_users[] = $row['User'];
        }
        $html .= '</table>';



        // --------------------------------------------------------------------
        //                               Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("All Laps") . "</h1>";
        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Grip") . '</th>';
        $laps = $acswuiDatabase->fetch_2d_array("Laps", ['User', 'Laptime', 'Cuts', 'Grip', 'CarSkin', 'Id'], ['Session'=>$this->SessionId], "Id", FALSE);

        // determine minimum lap id
        $laps_min_id = Null;
        foreach ($laps as $l) {
            if ($laps_min_id === NUll || $l['Id'] < $laps_min_id) $laps_min_id = $l['Id'];
        }

        // find latest lap per driver
        $latest_lap_drivers = array();
        for ($i=0; $i < count($laps); ++$i) {
            if (in_array($laps[$i]['User'], $latest_lap_drivers)) {
                $laps[$i]['LatestLap'] = FALSE;
            } else {
                $laps[$i]['LatestLap'] = TRUE;
                $latest_lap_drivers[] = $laps[$i]['User'];
            }
        }


        // sort best lap times
        function compare_laptimes($l1, $l2) {
            return ($l1['Laptime'] < $l2['Laptime']) ? -1 : 1;
        }
        uasort($laps, 'compare_laptimes');


        // dump laps
        foreach ($laps as $l) {

            $class = "class=\"";
            $class .= ($l['Cuts'] > 0) ? " lap_invalid" : "";
            $class .= ($l['LatestLap']) ? " lap_latest" : "";
            $class .= "\"";

            $html .= "<tr $class>";
            $html .= "<td>" . ($l['Id'] - $laps_min_id + 1) . "</td>";
            $html .= "<td>" . HumanValue::format($row['Laptime'], "LAPTIME") . "</td>";
            $html .= "<td>" . $l['Cuts'] . "</td>";
            $html .= "<td>" . $drivers[$l['User']] . "</td>";
            $html .= "<td>" . $this->getCarName($l['CarSkin']) . "</td>";
            $html .= "<td>" . sprintf("%0.1f", 100 * $l['Grip']) . "&percnt;</td>";
            $html .= '</tr>';
        }
        $html .= '</table>';



        return $html;
    }

}

?>
