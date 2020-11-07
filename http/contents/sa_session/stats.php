<?php

class stats extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Status");
        $this->PageTitle  = "Session Status";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Session"];

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
            $html .= "<td>" . laptime2str($row['Laptime']) . "</td>";
            $html .= "<td>" . $drivers[$row['User']] . "</td>";
            $html .= "<td>" . $this->getCarName($row['CarSkin']) . "</td>";
            $html .= "<td>" . (100 * $row['Grip']) . "&percnt;</td>";
            $html .= '</tr>';

            $listed_users[] = $row['User'];
        }
        $html .= '</table>';



        // --------------------------------------------------------------------
        //                               Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Laps") . "</h1>";
        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Grip") . '</th>';
        $laps = $acswuiDatabase->fetch_2d_array("Laps", ['User', 'Laptime', 'Cuts', 'Grip', 'CarSkin'], ['Session'=>$this->SessionId], "Id", FALSE);
        $lap_idx = count($laps);
        foreach ($laps as $row) {

            $class = ($row['Cuts'] > 0) ? "class=\"lap_invalid\"" : "";

            $html .= "<tr $class>";
            $html .= "<td>$lap_idx</td>";
            $html .= "<td>" . laptime2str($row['Laptime']) . "</td>";
            $html .= "<td>" . $row['Cuts'] . "</td>";
            $html .= "<td>" . $drivers[$row['User']] . "</td>";
            $html .= "<td>" . $this->getCarName($row['CarSkin']) . "</td>";
            $html .= "<td>" . (100 * $row['Grip']) . "&percnt;</td>";
            $html .= '</tr>';

            $lap_idx -= 1;
        }
        $html .= '</table>';



        return $html;
    }

}

?>
