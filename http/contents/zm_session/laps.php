<?php

class laps extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Laps");
        $this->PageTitle  = "Session Laps";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session_View"];

        // class local vars
        $this->Session = Null;
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
            $this->Session = new Session((int) $_GET['SESSION_ID']);
            $_SESSION['SESSION_ID'] = $this->Session->id();
        } else if (isset($_SESSION['SESSION_ID'])) {
            $this->Session = new Session((int) $_SESSION['SESSION_ID']);
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
            if ($this->Session === Null) {
                $this->Session = new Session($row['Id']);
            }

            // get track
            $track = "";
            $tracks = $acswuiDatabase->fetch_2d_array("Tracks", ['Name'], ['Id'=>$row['Track']]);
            if (count($tracks) == 1) {
                $track = $tracks[0]['Name'];
            }

            $selected = ($this->Session->id() == $row['Id']) ? "selected" : "";
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

        // get laps, ordered by laptime
        $laps = array();
        $laps = $this->Session->drivenLaps();

        // laps ordered by laptime
        $best_laps = array();
        foreach ($laps as $lap) {
            if ($lap->cuts() == 0) $best_laps[] = $lap;
        }
        function compare_laptime($l1, $l2) {
            return ($l1->laptime() < $l2->laptime()) ? -1 : 1;
        }
        usort($best_laps, "compare_laptime");

        // get best laptime
        $laptime_best = NULL;
        foreach ($laps as $lap) {
            if ($laptime_best === NULL || $lap->laptime() < $laptime_best)
                $laptime_best = $lap->laptime();
        }



        // --------------------------------------------------------------------
        //                               Session Info
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Session Info") . "</h1>";

        // sesion info
        $html .= '<table>';

        $html .= '<tr>';
        $html .= '<th>' . _("Server/Session Name") . '</th>';
        $html .= '<th>' . _("Track") . '</th>';
        $html .= '<th>' . (($this->Session->laps() == 0) ? _("Time") : _("Laps")) . '</th>';
        $html .= '<th>' . _("Temp Amb / Road") . '</th>';
        $html .= '<th>' . _("Grip") . '</th>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td>' . $this->Session->serverName() . " / " . $this->Session->name() . '</td>';
        $html .= '<td>' . $this->Session->track()->name() . '</td>';
        $html .= '<td>' . (($this->Session->laps() == 0) ? $this->Session->time() : $this->Session->laps()) . '</td>';
        $html .= '<td>' . HumanValue::format($this->Session->tempAmb(), "°C") . " / " . HumanValue::format($this->Session->tempRoad(), "°C") . '</td>';
        $html .= '<td>';
        if (count($this->Session->drivenLaps()) > 0) {
            $html .= HumanValue::format($this->Session->drivenLaps()[count($this->Session->drivenLaps()) - 1]->grip() * 100, "%");
            $html .= " - ";
            $html .= HumanValue::format($this->Session->drivenLaps()[0]->grip() * 100, "%");
        }
        $html .= '</td>';
        $html .= '</tr>';

        $html .= '</table>';

        // Driver Summary
        $html .= '<table>';

        $html .= '<tr>';
        $html .= '<th>' . _("Driver") . '</th>';
        $html .= '<th colspan="3">' . _("Driven") . '</th>';
        $html .= '</tr>';

        foreach ($this->Session->drivers() as $user) {

            $lap_count = 0;
            foreach ($laps as $lap) {
                if ($lap->user()->id() == $user->id()) ++$lap_count;
            }

            $html .= '<tr>';
            $html .= '<td>' . $user->login() . '</th>';
            $html .= '<td>' . HumanValue::format($lap_count, "laps") . '</td>';
            $html .= '<td>' . HumanValue::format($lap_count * $this->Session->track()->length(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';





        // --------------------------------------------------------------------
        //                               Race Standings
        // --------------------------------------------------------------------

        if ($this->Session->type() == 3) {
            $html .= "<h1>" . _("Race Standings") . "</h1>";

            # race position diagram
            $svg_path = $acswuiConfig->AcsContent . "/session_standing_diagrams/session_" . $this->Session->id() . ".svg";
            if (file_exists($svg_path)) {
                $html .= "<img src=\"$svg_path\" class=\"session_lap_diagram\">";
            }
        }



        // --------------------------------------------------------------------
        //                               Fastest Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Fastest Laps") . "</h1>";

        # diagram
        $svg_path = $acswuiConfig->AcsContent . "/session_lap_diagrams/session_" . $this->Session->id() . ".svg";
        if (file_exists($svg_path)) {
            $html .= "<img src=\"$svg_path\" class=\"session_lap_diagram\">";
        }

        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th>';

        $listed_user_ids = array();
        foreach ($best_laps as $lap) {
            if (in_array($lap->user()->id(), $listed_user_ids)) continue;

            $lap_number = $lap->id() - $this->Session->firstDrivenLap()->id() + 1;

            $class = "class=\"";
            $class .= ($lap->cuts() > 0) ? " lap_invalid" : "";
            $class .= "\"";

            $html .= "<tr $class>";
            $html .= "<td>$lap_number</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime(), "LAPTIME") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime() - $laptime_best, "ms") . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->login() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . HumanValue::format($lap->ballast(), "kg") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->restrictor(), "%") . "</td>";
            $html .= "<td>" . HumanValue::format(100 * $lap->grip(), "%") . "</td>";
            $html .= "<td>" . $lap->carSkin()->htmlImg("", 100) . "</td>";
            $html .= '</tr>';

            $listed_user_ids[] = $lap->user()->id();
        }
        $html .= '</table>';



        // --------------------------------------------------------------------
        //                                 All Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("All Laps") . "</h1>";

        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Lap Id") . '</th>';
        foreach ($laps as $lap) {
            $class = "class=\"";
            $class .= ($lap->cuts() > 0) ? " lap_invalid" : "";
            $class .= "\"";

            $lap_number = $lap->id() - $this->Session->firstDrivenLap()->id() + 1;

            $html .= "<tr $class>";
            $html .= "<td>" . $lap_number . "</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime(), "LAPTIME") . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->login() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . HumanValue::format($lap->ballast(), "kg") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->restrictor(), "%") . "</td>";
            $html .= "<td>" . HumanValue::format(100 * $lap->grip(), "%") . "</td>";
            $html .= "<td>" . $lap->id() . "</td>";
            $html .= '</tr>';
        }
        $html .= '</table>';




        return $html;
    }

}

?>
