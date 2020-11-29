<?php

class laps extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Laps");
        $this->PageTitle  = "Session Laps";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session_View"];

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
            $name = "[" . $row['Id'] . "] " . $row['Timestamp'] . " - $track - " . $row['Name'];
            $html .= "<option value=\"$id\" $selected >$name</option>";
        }
        $html .= '</select>';
        $html .= '<br>';
        $html .= '</form>';



        // --------------------------------------------------------------------
        //                          Get Session Data
        // --------------------------------------------------------------------

        # check if session diagram exists
        $svg_path = $acswuiConfig->AcsContent . "/session_lap_diagrams/session_" . $this->SessionId . ".svg";
        if (file_exists($svg_path)) {
            $html .= "<img src=\"$svg_path\" class=\"session_lap_diagram\">";
        }


        $session = new Session($this->SessionId);

        // get laps, ordered by laptime
        $laps = array();
        $laps = $session->drivenLaps();
        function compare_laptime($l1, $l2) {
            return ($l1->laptime() < $l2->laptime()) ? -1 : 1;
        }


        // html dump
        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th>';
        foreach ($laps as $lap) {
            $class = "class=\"";
            $class .= ($lap->cuts() > 0) ? " lap_invalid" : "";
            $class .= "\"";

            $lap_number = $lap->id() - $session->firstDrivenLap()->id() + 1;

            $html .= "<tr $class>";
            $html .= "<td>" . $lap_number . "</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime(), "LAPTIME") . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->login() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . HumanValue::format($lap->ballast(), "kg") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->restrictor(), "%") . "</td>";
            $html .= "<td>" . HumanValue::format(100 * $lap->grip(), "%") . "</td>";
            $html .= '</tr>';
        }
        $html .= '</table>';




        return $html;
    }

}

?>
